<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->morphs('remindable'); // remindable_type + remindable_id + index

            $table->string('type', 40);        // task_due_7d, task_due_3d, task_due_2d, task_due_1d, task_due_today, task_overdue, meeting_upcoming
            $table->dateTime('scheduled_for');
            $table->string('channel', 20)->default('in_app'); // in_app | email | both

            $table->string('status', 20)->default('pending'); // pending | sent | failed | cancelled

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();

            $table->timestamps();

            $table->index(['status', 'scheduled_for']); // the scheduler's hot path
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};