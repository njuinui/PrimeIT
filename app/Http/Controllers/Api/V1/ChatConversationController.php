<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Chat\ConversationService;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Support\ChatPayload;
use App\Support\PersonPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ChatConversationController extends Controller
{
    public function __construct(private readonly ConversationService $service) {}

    /** Everything the user can talk in, most recent first. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->can('chat.use'), 403);

        $this->service->bootstrapFor($user);

        $conversations = $this->listFor($user);
        $unread        = $this->unreadByConversation($user, $conversations->pluck('id')->all());

        return $this->success(
            $conversations
                ->map(fn (Conversation $c) => ChatPayload::conversation($c, $user, (int) ($unread[$c->id] ?? 0)))
                ->values()
                ->all(),
        );
    }

    /**
     * Open (or create) a conversation:
     *   direct  → { type, user_id }
     *   group   → { type, name, user_ids[] }
     *   team    → { type, team_id }
     *   project → { type, project_id }
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->can('chat.use'), 403);

        $type = (string) $request->validate(['type' => ['required', Rule::in(Conversation::TYPES)]])['type'];

        $conversation = match ($type) {
            'direct'  => $this->openDirect($request, $user),
            'group'   => $this->openGroup($request, $user),
            'team'    => $this->openTeam($request, $user),
            'project' => $this->openProject($request, $user),
        };

        return $this->success($this->payloadFor($conversation, $user), 'Conversation ready.', 201);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        return $this->success($this->payloadFor($conversation, $request->user()));
    }

    public function read(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $conversation->participants()->where('user_id', $request->user()->id)->update(['last_read_at' => now()]);

        return $this->success(null);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->can('chat.use'), 403);

        $ids = DB::table('conversation_participants')->where('user_id', $user->id)->pluck('conversation_id')->all();

        return $this->success(['count' => (int) array_sum($this->unreadByConversation($user, $ids))]);
    }

    /** People a user can start a direct message with. */
    public function people(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->can('chat.use'), 403);

        $search = trim((string) $request->query('search', ''));

        $people = User::query()
            ->with('employee')
            ->where('status', 'active')
            ->whereKeyNot($user->id)
            ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')
            ->limit(25)
            ->get();

        return $this->success($people->map(fn (User $u) => PersonPayload::make($u))->all());
    }

    // ── Openers ───────────────────────────────────────────────

    private function openDirect(Request $request, User $user): Conversation
    {
        $data = $request->validate(['user_id' => ['required', 'uuid']]);

        $other = User::query()->where('uuid', $data['user_id'])->where('status', 'active')->firstOrFail();

        abort_if($other->id === $user->id, 422, 'You cannot message yourself.');

        return $this->service->direct($user, $other);
    }

    private function openGroup(Request $request, User $user): Conversation
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:120'],
            'user_ids'   => ['required', 'array', 'min:1', 'max:50'],
            'user_ids.*' => ['uuid'],
        ]);

        $ids = User::query()->whereIn('uuid', $data['user_ids'])->where('status', 'active')->pluck('id');

        abort_if($ids->isEmpty(), 422, 'Pick at least one other active person.');

        return $this->service->group($user, $data['name'], $ids);
    }

    private function openTeam(Request $request, User $user): Conversation
    {
        $data = $request->validate(['team_id' => ['required', 'uuid']]);

        $team = Team::query()->where('uuid', $data['team_id'])->firstOrFail();

        $conversation = $this->service->forTeam($team);

        abort_unless($conversation->hasParticipant($user), 403, 'Only team members can join the team chat.');

        return $conversation;
    }

    private function openProject(Request $request, User $user): Conversation
    {
        $data = $request->validate(['project_id' => ['required', 'uuid']]);

        $project = Project::query()->where('uuid', $data['project_id'])->firstOrFail();

        $this->authorize('view', $project);

        $conversation = $this->service->forProject($project);

        abort_unless($conversation->hasParticipant($user), 403, 'Only project members can join the project chat.');

        return $conversation;
    }

    // ── Helpers ───────────────────────────────────────────────

    private function listFor(User $user)
    {
        return Conversation::query()
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->with(['team:id,uuid,name', 'project:id,uuid,name', 'participants.user.employee', 'latestMessage.user'])
            ->orderByRaw('last_message_at IS NULL')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();
    }

    private function payloadFor(Conversation $conversation, User $user): array
    {
        $conversation->load(['team:id,uuid,name', 'project:id,uuid,name', 'participants.user.employee', 'latestMessage.user']);

        $unread = $this->unreadByConversation($user, [$conversation->id]);

        return ChatPayload::conversation($conversation, $user, (int) ($unread[$conversation->id] ?? 0));
    }

    /**
     * @param int[] $conversationIds
     * @return array<int, int> conversation id => unread messages from other people
     */
    private function unreadByConversation(User $user, array $conversationIds): array
    {
        if ($conversationIds === []) {
            return [];
        }

        return DB::table('messages')
            ->join('conversation_participants as cp', function ($join) use ($user) {
                $join->on('cp.conversation_id', '=', 'messages.conversation_id')->where('cp.user_id', '=', $user->id);
            })
            ->whereIn('messages.conversation_id', $conversationIds)
            ->whereNull('messages.deleted_at')
            ->where(fn ($q) => $q->whereNull('messages.user_id')->orWhere('messages.user_id', '!=', $user->id))
            ->where(fn ($q) => $q->whereNull('cp.last_read_at')->orWhereColumn('messages.created_at', '>', 'cp.last_read_at'))
            ->selectRaw('messages.conversation_id as cid, COUNT(*) as total')
            ->groupBy('messages.conversation_id')
            ->pluck('total', 'cid')
            ->map(fn ($n) => (int) $n)
            ->all();
    }
}
