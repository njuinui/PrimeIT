<?php

namespace App\Console\Commands;

use App\Domain\Reminders\SendDueReminders;
use Illuminate\Console\Command;

class SendRemindersCommand extends Command
{
    protected $signature = 'reminders:send';

    protected $description = 'Deliver due deadline reminders through the notification pipeline';

    public function handle(SendDueReminders $sender): int
    {
        $stats = $sender();

        $this->info(sprintf(
            'Reminders dispatched — %d sent, %d cancelled, %d failed.',
            $stats['sent'], $stats['cancelled'], $stats['failed'],
        ));

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}