<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('task_number')->unique();

            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_task_id')->nullable()->constrained('tasks')->nullOnDelete();

            $table->string('title');
            $table->string('slug')->index();

            $table->longText('description')->nullable();

            $table->string('status', 20)->default('todo');
            $table->string('priority', 20)->default('medium');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->dateTime('start_date')->nullable();
            $table->dateTime('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->unsignedInteger('actual_minutes')->default(0);

            $table->unsignedTinyInteger('progress')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_recurring')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status']);
            $table->index('due_date');
            $table->index(['status', 'due_date']); // powers the reminder engine (Phase 9)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};