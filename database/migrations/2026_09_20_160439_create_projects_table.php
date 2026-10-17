<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('project_number')->unique();

            $table->string('name');
            $table->string('slug')->unique();
            $table->longText('description')->nullable();

            $table->string('status', 20)->default('planning');
            $table->string('priority', 20)->default('medium');

            $table->foreignId('owner_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('project_manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();

            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->decimal('budget', 15, 2)->nullable();
            $table->string('currency', 3)->default('XAF');

            $table->unsignedTinyInteger('progress')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'priority']);
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};