<?php

namespace App\Console\Commands;

use App\Domain\Approvals\ApprovalEngine;
use Illuminate\Console\Command;

class EscalateApprovalsCommand extends Command
{
    protected $signature = 'approvals:escalate';

    protected $description = 'Escalate stale approval steps to the requester\'s manager and notify them';

    public function handle(ApprovalEngine $engine): int
    {
        $count = $engine->escalateStale();

        $this->info(sprintf('Approval escalation complete — %d step(s) escalated.', $count));

        return self::SUCCESS;
    }
}