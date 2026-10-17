<?php

namespace App\Console\Commands;

use App\Domain\Reminders\ScanMeetingReminders;
use App\Domain\Reminders\ScanTaskReminders;
use Illuminate\Console\Command;

class ScanRemindersCommand extends Command
{
    protected $signature = 'reminders:scan';

    protected $description = 'Scan tasks and meetings, queue deadline reminders (7/3/2/1 days, today, overdue, escalation, meeting 24h/15m)';

    public function handle(ScanTaskReminders $tasks, ScanMeetingReminders $meetings): int
    {
        $taskStats    = $tasks();
        $meetingStats = $meetings();

        $created = $taskStats['created'] + $meetingStats['created'];
        $byType  = array_merge($taskStats['by_type'], $meetingStats['by_type']);

        $this->info(sprintf('Reminder scan complete — %d new reminder(s) queued.', $created));

        foreach ($byType as $type => $count) {
            $this->line(sprintf('  %-26s %d', $type, $count));
        }

        return self::SUCCESS;
    }
}