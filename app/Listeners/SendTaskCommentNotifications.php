<?php

namespace App\Listeners;

use App\Domain\Notifications\TaskNotificationRecipients;
use App\Events\TaskCommentAdded;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\Tasks\TaskCommentedNotification;
use App\Notifications\Tasks\TaskMentionedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;

class SendTaskCommentNotifications implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly TaskNotificationRecipients $recipients) {}

    public function handle(TaskCommentAdded $event): void
    {
        $event->task->loadMissing('project');

        $mentioned = $this->resolveMentions($event->comment->mentions ?? [], $event->comment->user_id);

        // 1) Mention notifications — highest priority, always separate.
        $mentioned->each(fn (User $user) => $user->notify(
            new TaskMentionedNotification($event->task, $event->comment, $event->actor),
        ));

        // 2) Comment notifications — everyone with a stake except the
        //    author, the actor is the author here, and the mentioned
        //    users (they already got the louder mention).
        $mentionedIds = $mentioned->pluck('id');

        $userIds = $this->recipients
            ->forTask($event->task)
            ->reject(fn ($id) => $id === $event->comment->user_id)
            ->reject(fn ($id) => $mentionedIds->contains($id));

        if ($userIds->isEmpty()) {
            return;
        }

        User::query()->whereIn('id', $userIds->all())->get()->each(
            fn (User $user) => $user->notify(
                new TaskCommentedNotification($event->task, $event->comment, $event->actor),
            ),
        );
    }

    /**
     * The Phase 7 regex captures greedily ("@Dana Designer please review"),
     * so we match known names as *prefixes* of each captured string —
     * against both account names and employee full names.
     *
     * @param  array<int, string>|null $mentions
     * @return Collection<int, User>
     */
    private function resolveMentions(?array $mentions, int $authorId): Collection
    {
        if ($mentions === null || $mentions === []) {
            return new Collection();
        }

        $lowered = array_map(
            fn (string $mention) => mb_strtolower(trim($mention)),
            $mentions,
        );

        $names = User::query()->get(['id', 'name'])
            ->mapWithKeys(fn (User $user) => [mb_strtolower($user->name) => $user->id]);

        Employee::query()->whereNotNull('user_id')->get()
            ->each(function (Employee $employee) use (&$names): void {
                $fullName = mb_strtolower(trim($employee->first_name.' '.$employee->last_name));

                if (! $names->has($fullName)) {
                    $names->put($fullName, $employee->user_id);
                }
            });

        // Longest names first so "Dana Designer" wins over a shorter prefix.
        $sorted = $names->sortKeysDesc(fn (string $a, string $b) => strlen($b) <=> strlen($a));

        $matchedIds = [];

        foreach ($lowered as $captured) {
            foreach ($sorted as $name => $userId) {
                if (str_starts_with($captured, $name)) {
                    $matchedIds[] = $userId;
                    break;
                }
            }
        }

        if ($matchedIds === []) {
            return new Collection();
        }

        return User::query()
            ->whereIn('id', array_values(array_unique($matchedIds)))
            ->get()
            ->reject(fn (User $user) => $user->id === $authorId);
    }
}