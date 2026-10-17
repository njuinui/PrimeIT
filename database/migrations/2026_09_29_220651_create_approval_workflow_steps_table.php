<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_workflow_steps', function (Blueprint $table) {
            $table->id();

            $table->foreignId('approval_workflow_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('step_number');
            $table->string('name', 100);

            $table->string('approver_source', 30);          // role|requester_manager|specific_users
            $table->string('approver_role', 100)->nullable();
            $table->json('approver_user_ids')->nullable();  // user ids for specific_users

            $table->string('policy', 20)->default('any_one'); // any_one|all
            $table->unsignedSmallInteger('escalation_days')->nullable();

            $table->timestamps();

            $table->unique(['approval_workflow_id', 'step_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_workflow_steps');
    }
};