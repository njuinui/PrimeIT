<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_histories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('event', 30);                 // created|submitted|approved|rejected|paid|payment_removed
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->string('note', 500)->nullable();

            $table->timestamp('created_at')->nullable()->useCurrent();

            $table->index(['expense_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_histories');
    }
};