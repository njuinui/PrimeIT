<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Meetings\ConvertActionItems;
use App\Http\Controllers\Controller;
use App\Http\Requests\Meetings\RespondMeetingRequest;
use App\Http\Requests\Meetings\SaveMinutesRequest;
use App\Http\Requests\Meetings\StoreMeetingRequest;
use App\Http\Requests\Meetings\StoreParticipantsRequest;
use App\Http\Requests\Meetings\UpdateMeetingRequest;
use App\Http\Resources\V1\MeetingResource;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingMinutes;
use App\Models\Reminder;
use App\Models\User;
use App\Notifications\Meetings\MeetingCancelledNotification;
use App\Notifications\Meetings\MeetingInvitedNotification;
use App\Notifications\Meetings\MeetingMinutesPublishedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MeetingController extends Controller
{
    private const RELATIONS = ['organizer', 'participants', 'minutes'];

    public function __construct(private readonly ConvertActionItems $convertActionItems) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('meetings.view');

        $user    = $request->user();
        $search  = trim((string) $request->query('search'));
        $status  = (string) $request->query('status');
        $from    = (string) $request->query('from');
        $to      = (string) $request->query('to');
        $window  = (string) $request->query('window', 'all'); // all|upcoming|past

        $query = Meeting::query()->with(self::RELATIONS);

        // meetings.manage holders see everything; everyone else only their own.
        if (! $user->can('meetings.manage')) {
            $employee = $user->employee()->first();

            $query->where(function ($inner) use ($employee) {
                if ($employee === null) {
                    return $inner->whereRaw('1 = 0');
                }

                $inner->where('organized_by', $employee->id)
                    ->orWhereHas('participants', fn ($p) => $p->where('meeting_participants.employee_id', $employee->id));
            });
        }

        $query
            ->when($search !== '', fn ($q) => $q->where('title', 'like', "%{$search}%"))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($from !== '' && strtotime($from) !== false, fn ($q) => $q->whereDate('starts_at', '>=', $from))
            ->when($to !== '' && strtotime($to) !== false, fn ($q) => $q->whereDate('starts_at', '<=', $to))
            ->when($window === 'upcoming', fn ($q) => $q->upcoming())
            ->when($window === 'past', fn ($q) => $q->past());

        $meetings = $query
            ->orderByRaw('starts_at IS NULL')
            ->orderBy($window === 'past' ? 'starts_at' : 'starts_at', $window === 'past' ? 'desc' : 'asc')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return $this->paginated($meetings, MeetingResource::class);
    }

    public function store(StoreMeetingRequest $request): JsonResponse
    {
        $this->authorize('create', Meeting::class);

        $user = $request->user();
        $data = collect($request->validated())->except('participants')->all();

        $meeting = DB::transaction(function () use ($data, $request, $user): Meeting {
            $meeting = Meeting::create([
                ...$data,
                'created_by' => $user->id,
            ]);

            // Organizer joins automatically as a required, attending participant.
            $attach = [];

            if ($meeting->organized_by !== null) {
                $attach[$meeting->organized_by] = [
                    'attendance' => 'required', 'response' => 'going',
                    'responded_at' => now(), 'added_by' => $user->id,
                ];
            }

            foreach ($request->validated('participants', []) as $participant) {
                $employeeId = Employee::query()->where('uuid', $participant['employee_id'])->value('id');

                if ($employeeId !== null && ! isset($attach[$employeeId])) {
                    $attach[$employeeId] = [
                        'attendance' => $participant['attendance'],
                        'added_by'   => $user->id,
                    ];
                }
            }

            $meeting->participants()->attach($attach);

            return $meeting;
        });

        $this->notifyParticipants($meeting->fresh(), $user, exceptUserId: $user->id);

        return $this->success(new MeetingResource($meeting->fresh()->loadMissing(self::RELATIONS)), 'Meeting created.', 201);
    }

    public function show(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $meeting->loadMissing(self::RELATIONS);

        return $this->success(new MeetingResource($meeting));
    }

    public function update(UpdateMeetingRequest $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('update', $meeting);

        $originalStart = $meeting->starts_at;

        $meeting->update($request->validated());
        $meeting = $meeting->fresh();

        // Rescheduling kills stale reminders — the next scan re-creates the right ones.
        if ($originalStart->toDateTimeString() !== $meeting->starts_at->toDateTimeString()) {
            Reminder::cancelForRemindable($meeting);
        }

        return $this->success(new MeetingResource($meeting->loadMissing(self::RELATIONS)), 'Meeting updated.');
    }

    public function cancel(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('update', $meeting);

        if ($meeting->status !== 'scheduled') {
            return $this->error('Only scheduled meetings can be cancelled — this one is '.$meeting->status.'.', 409);
        }

        $meeting->forceFill(['status' => 'cancelled'])->save();
        Reminder::cancelForRemindable($meeting);

        $this->notifyAll($meeting->fresh(), $request->user(), new MeetingCancelledNotification($meeting->fresh(), $request->user()));

        return $this->success(new MeetingResource($meeting->fresh()->loadMissing(self::RELATIONS)), 'Meeting cancelled.');
    }

    public function complete(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('update', $meeting);

        if ($meeting->status !== 'scheduled') {
            return $this->error('Only scheduled meetings can be completed.', 409);
        }

        $meeting->forceFill(['status' => 'completed'])->save();
        Reminder::cancelForRemindable($meeting);

        return $this->success(new MeetingResource($meeting->fresh()->loadMissing(self::RELATIONS)), 'Meeting completed.');
    }

    public function destroy(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('delete', $meeting);

        Reminder::cancelForRemindable($meeting);

        $meeting->delete();

        return $this->success(message: 'Meeting deleted.');
    }

    // ── Participation ─────────────────────────────────────────

    public function respond(RespondMeetingRequest $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('respond', $meeting);

        if ($meeting->status !== 'scheduled') {
            return $this->error('This meeting is no longer scheduled.', 409);
        }

        $employee = $request->user()->employee()->first();

        $meeting->participants()->updateExistingPivot($employee->id, [
            'response'     => $request->validated('response'),
            'responded_at' => now(),
        ]);

        return $this->success(new MeetingResource($meeting->fresh()->loadMissing(self::RELATIONS)), 'Response recorded.');
    }

    public function addParticipants(StoreParticipantsRequest $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('update', $meeting);

        $existing = $meeting->participants()->pluck('employees.id');

        $new = Employee::query()
            ->whereIn('uuid', $request->validated('employees'))
            ->get()
            ->reject(fn (Employee $employee) => $existing->contains($employee->id));

        if ($new->isNotEmpty()) {
            $meeting->participants()->attach(
                $new->mapWithKeys(fn (Employee $employee) => [
                    $employee->id => [
                        'attendance' => $request->validated('attendance'),
                        'added_by'   => $request->user()->id,
                    ],
                ])->all(),
            );

            $this->notifyParticipants($meeting->fresh(), $request->user(), $new->pluck('id')->all());
        }

        return $this->success(
            new MeetingResource($meeting->fresh()->loadMissing(self::RELATIONS)),
            $new->isEmpty() ? 'No new participants to add.' : sprintf('%d participant(s) added.', $new->count()),
        );
    }

    public function removeParticipant(Request $request, Meeting $meeting, Employee $employee): JsonResponse
    {
        $this->authorize('update', $meeting);

        if (! $meeting->participants()->where('employees.id', $employee->id)->exists()) {
            return $this->error('This employee is not a participant.', 404);
        }

        $meeting->participants()->detach($employee->id);

        return $this->success(new MeetingResource($meeting->fresh()->loadMissing(self::RELATIONS)), 'Participant removed.');
    }

    // ── Minutes ───────────────────────────────────────────────

    public function saveMinutes(SaveMinutesRequest $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('manageMinutes', $meeting);

        $minutes = MeetingMinutes::updateOrCreate(
            ['meeting_id' => $meeting->id],
            [
                'content'      => $request->validated('content'),
                'action_items' => array_values($request->validated('action_items')),
                'recorded_by'  => $request->user()->id,
            ],
        );

        return $this->success(
            new MeetingResource($meeting->fresh()->loadMissing(self::RELATIONS)),
            $minutes->published_at !== null ? 'Minutes updated.' : 'Minutes saved as draft.',
        );
    }

    public function publishMinutes(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('manageMinutes', $meeting);

        $minutes = $meeting->minutes()->first();

        if ($minutes === null || trim((string) $minutes->content) === '') {
            return $this->error('Record the minutes before publishing.', 409);
        }

        if ($minutes->published_at !== null) {
            return $this->error('These minutes are already published.', 409);
        }

        $minutes->forceFill(['published_at' => now()])->save();

        $actionCount = count(array_filter(
            $minutes->action_items ?? [],
            fn ($item) => ($item['task_id'] ?? null) === null,
        ));

        $this->notifyAll(
            $meeting->fresh(),
            $request->user(),
            new MeetingMinutesPublishedNotification($meeting->fresh(), $request->user(), $actionCount),
        );

        return $this->success(new MeetingResource($meeting->fresh()->loadMissing(self::RELATIONS)), 'Minutes published.');
    }

    public function convertActionItems(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('manageMinutes', $meeting);

        $result = ($this->convertActionItems)($meeting, $request->user());

        return $this->success([
            'meeting'        => new MeetingResource($meeting->fresh()->loadMissing(self::RELATIONS)),
            'created_tasks'  => $result['created'],
        ], sprintf('%d action item(s) converted into tasks.', count($result['created'])));
    }

    // ── Internals ─────────────────────────────────────────────

    /** Notify the given (or all) participant accounts + organizer, minus the actor. */
    private function notifyParticipants(Meeting $meeting, User $actor, ?array $onlyEmployeeIds = null, ?int $exceptUserId = null): void
    {
        $query = $meeting->participants()->with('user');

        if ($onlyEmployeeIds !== null) {
            $query->whereIn('employees.id', $onlyEmployeeIds);
        }

        $except ??= $exceptUserId ?? $actor->id;

        $userIds = $query->get()
            ->map(fn ($participant) => $participant->user_id)
            ->push(
                $meeting->organized_by !== null
                    ? Employee::query()->where('id', $meeting->organized_by)->value('user_id')
                    : null,
            )
            ->filter()
            ->unique()
            ->reject(fn ($id) => $id === $except)
            ->values();

        if ($userIds->isEmpty()) {
            return;
        }

        $meeting->loadMissing('organizer');

        User::query()->whereIn('id', $userIds->all())->get()->each(
            fn (User $user) => $user->notify(new MeetingInvitedNotification($meeting, $actor)),
        );
    }

    /** Notify participants + organizer with an arbitrary notification, minus the actor. */
    private function notifyAll(Meeting $meeting, User $actor, $notification): void
    {
        $userIds = $meeting->participants()->pluck('employees.user_id')
            ->push(
                $meeting->organized_by !== null
                    ? Employee::query()->where('id', $meeting->organized_by)->value('user_id')
                    : null,
            )
            ->filter()
            ->unique()
            ->reject(fn ($id) => $id === $actor->id)
            ->values();

        if ($userIds->isEmpty()) {
            return;
        }

        User::query()->whereIn('id', $userIds->all())->get()->each(
            fn (User $user) => $user->notify($notification),
        );
    }
}