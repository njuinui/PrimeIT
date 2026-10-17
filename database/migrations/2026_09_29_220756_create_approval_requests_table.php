<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('workflow_id')->nullable()->constrained('approval_workflows')->nullOnDelete();
            $table->morphs('approvable');

            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();

            $table->string('status', 20)->default('pending'); // pending|approved|rejected|cancelled
            $table->unsignedSmallInteger('current_step')->default(1);

            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('requested_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};