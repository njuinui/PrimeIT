<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('approval_request_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('step_number');
            $table->string('name', 100);

            $table->string('approver_source', 30);
            $table->string('approver_role', 100)->nullable();
            $table->json('approver_user_ids')->nullable();
            $table->json('escalated_user_ids')->nullable();

            $table->string('policy', 20)->default('any_one');
            $table->unsignedSmallInteger('escalation_days')->nullable();

            $table->string('status', 20)->default('pending'); // pending|approved|rejected
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('escalated_at')->nullable();

            $table->timestamps();

            $table->index(['approval_request_id', 'step_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_steps');
    }
};