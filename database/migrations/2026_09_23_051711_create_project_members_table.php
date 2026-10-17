<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the project members pivot table.
     */
    public function up(): void
    {
        Schema::create('project_members', function (Blueprint $table) {
            $table->id();

            /*
             * Project this membership belongs to.
             */
            $table->foreignId('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();

            /*
             * Employee assigned to the project.
             */
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();

            /*
             * Employee's responsibility within the project.
             *
             * Examples:
             * - project_manager
             * - lead
             * - member
             */
            $table->string('role', 50)->default('member');

            /*
             * User who added this employee to the project.
             */
            $table->foreignId('added_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            /*
             * Prevent the same employee from being added
             * to the same project more than once.
             */
            $table->unique(
                ['project_id', 'employee_id'],
                'project_members_project_employee_unique'
            );

            /*
             * Useful indexes for project/member lookups.
             */
            $table->index('employee_id');
            $table->index('added_by');
            $table->index('role');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_members');
    }
};