<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_files', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();

            // figma | adobe_xd | sketch | other
            $table->string('provider', 20)->default('figma');
            $table->string('title');
            $table->text('url');
            $table->string('external_key')->nullable();

            // draft | in_review | changes_requested | approved | handed_off
            $table->string('status', 24)->default('draft');
            $table->unsignedTinyInteger('progress')->default(0);

            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status']);
            $table->unique(['project_id', 'provider', 'external_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_files');
    }
};
