<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A work update is the project-management equivalent of a commit:
        // who did what, on which project/task, how long it took, and how far
        // the work moved. It feeds the contribution graph and team insights.
        Schema::create('work_updates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('design_file_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // commit (manual log) | status (system) | review | design | note
            $table->string('kind', 20)->default('commit');
            // manual | system | figma | adobe_xd | sketch | other
            $table->string('source', 20)->default('manual');

            $table->string('message');
            $table->text('body')->nullable();

            $table->unsignedTinyInteger('progress_from')->nullable();
            $table->unsignedTinyInteger('progress_to')->nullable();
            $table->unsignedInteger('minutes_spent')->default(0);

            $table->timestamp('committed_at');
            $table->timestamps();

            $table->index(['project_id', 'committed_at']);
            $table->index(['user_id', 'committed_at']);
            $table->index(['task_id', 'committed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_updates');
    }
};
