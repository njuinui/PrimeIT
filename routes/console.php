<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Deadline reminder engine (Phase 9) ──────────────────────────
Schedule::command('reminders:scan')->everyFifteenMinutes();
Schedule::command('reminders:send')->everyFiveMinutes();

// ── Approval escalation (Phase 12) ──────────────────────────────
Schedule::command('approvals:escalate')->hourly();