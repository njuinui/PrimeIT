<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\Realtime\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Support\ChatPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ChatMessageController extends Controller
{
    private const PAGE = 30;

    /** Oldest-to-newest page; pass ?before=<message uuid> to load earlier ones. */
    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $query = $conversation->messages()
            ->with(['conversation:id,uuid', 'user.employee', 'replyTo.user'])
            ->orderByDesc('id');

        if ($request->filled('before')) {
            $beforeId = Message::query()
                ->where('conversation_id', $conversation->id)
                ->where('uuid', (string) $request->query('before'))
                ->value('id');

            $query->where('id', '<', $beforeId ?? 0);
        }

        $rows    = $query->limit(self::PAGE + 1)->get();
        $hasMore = $rows->count() > self::PAGE;

        $page = $rows->take(self::PAGE)->reverse()->values();

        return $this->success(
            $page->map(fn (Message $m) => ChatPayload::message($m))->all(),
            null,
            200,
            ['has_more' => $hasMore],
        );
    }

    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('send', $conversation);

        $data = $request->validate([
            'body'        => ['required', 'string', 'max:5000'],
            'reply_to_id' => ['nullable', 'uuid'],
        ]);

        $replyTo = ! empty($data['reply_to_id'])
            ? $conversation->messages()->where('uuid', $data['reply_to_id'])->first()
            : null;

        $message = $conversation->messages()->create([
            'user_id'     => $request->user()->id,
            'body'        => trim($data['body']),
            'reply_to_id' => $replyTo?->id,
        ]);

        $conversation->forceFill(['last_message_at' => $message->created_at])->save();

        // Sending counts as reading everything before it.
        $conversation->participants()->where('user_id', $request->user()->id)->update(['last_read_at' => now()]);

        $message->load(['conversation:id,uuid', 'user.employee', 'replyTo.user']);

        $payload = ChatPayload::message($message);

        $recipients = $conversation->participants()
            ->join('users', 'users.id', '=', 'conversation_participants.user_id')
            ->pluck('users.uuid')
            ->all();

        // Delivery is best-effort: if the websocket server is down the message is still
        // saved, and recipients see it on their next refresh.
        try {
            event(new MessageSent($payload, $recipients));
        } catch (Throwable $e) {
            report($e);
        }

        return $this->success($payload, null, 201);
    }

    public function update(Request $request, Message $message): JsonResponse
    {
        abort_unless($message->user_id === $request->user()->id, 403, 'You can only edit your own messages.');
        $this->authorize('send', Conversation::query()->findOrFail($message->conversation_id));

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $message->forceFill(['body' => trim($data['body']), 'edited_at' => now()])->save();

        return $this->success(ChatPayload::message($message->load(['conversation:id,uuid', 'user.employee', 'replyTo.user'])));
    }

    public function destroy(Request $request, Message $message): JsonResponse
    {
        abort_unless($message->user_id === $request->user()->id, 403, 'You can only delete your own messages.');

        $message->delete();

        return $this->success(null, 'Message deleted.');
    }
}
