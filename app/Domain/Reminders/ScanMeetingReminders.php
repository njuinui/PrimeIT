<?php

namespace App\Domain\Reminders;

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\Reminder;
use Illuminate\Support\Facades\DB;

final class ScanMeetingReminders
{
    public const TYPE_24H = 'meeting_upcoming_24h';
    public const TYPE_15M = 'meeting_upcoming_15m';

    /**
     * @return array{created: int, by_type: array<string, int>}
     */
    public function __invoke(): array
    {
        $stats = ['created' => 0, 'by_type' => []];

        $record = function (Meeting $meeting, int $userId, string $type) use (&$stats): void {
            $reminder = Reminder::firstOrCreate(
                [
                    'user_id'         => $userId,
                    'remindable_type' => $meeting->getMorphClass(),
                    'remindable_id'   => $meeting->id,
                    'type'            => $type,
                ],
                [
                    'scheduled_for' => now(),
                    'channel'       => 'in_app',
                    'status'        => Reminder::STATUS_PENDING,
                ],
            );

            if ($reminder->wasRecentlyCreated) {
                $stats['created']++;
                $stats['by_type'][$type] = ($stats['by_type'][$type] ?? 0) + 1;
            }
        };

        // 24-hour window — still in the future, starting within a day.
        Meeting::query()
            ->where('status', 'scheduled')
            ->where('starts_at', '>', now())
            ->where('starts_at', '<=', now()->addDay())
            ->get()
            ->each(function (Meeting $meeting) use ($record): void {
                foreach ($this->recipientUserIds($meeting) as $userId) {
                    $record($meeting, $userId, self::TYPE_24H);
                }
            });

        // 15-minute window.
        Meeting::query()
            ->where('status', 'scheduled')
            ->where('starts_at', '>', now())
            ->where('starts_at', '<=', now()->addMinutes(15))
            ->get()
            ->each(function (Meeting $meeting) use ($record): void {
                foreach ($this->recipientUserIds($meeting) as $userId) {
                    $record($meeting, $userId, self::TYPE_15M);
                }
            });

        return $stats;
    }

    /**
     * Participant accounts + the organizer's account.
     *
     * @return array<int, int>
     */
    private function recipientUserIds(Meeting $meeting): array
    {
        $participantUserIds = DB::table('meeting_participants as mp')
            ->join('employees as e', 'e.id', '=', 'mp.employee_id')
            ->where('mp.meeting_id', $meeting->id)
            ->whereNull('e.deleted_at')
            ->pluck('e.user_id');

        $organizerUserId = $meeting->organized_by !== null
            ? Employee::query()->where('id', $meeting->organized_by)->value('user_id')
            : null;

        return $participantUserIds
            ->merge([$organizerUserId])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}