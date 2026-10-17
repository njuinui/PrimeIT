<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('expense_number')->unique();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();

            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title', 200);
            $table->text('description')->nullable();

            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('XAF');
            $table->date('expense_date');

            $table->string('status', 20)->default('draft'); // draft|submitted|approved|rejected|paid

            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 1000)->nullable();

            $table->timestamp('paid_at')->nullable();

            $table->string('receipt_path')->nullable();
            $table->string('receipt_name', 200)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'expense_date']);
            $table->index('user_id');
            $table->index('expense_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};