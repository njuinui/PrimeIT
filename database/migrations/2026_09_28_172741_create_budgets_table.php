<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Exactly one of these is set (enforced in validation).
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();

            $table->string('description', 255)->nullable();

            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('XAF');

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('project_id');
            $table->index('department_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};