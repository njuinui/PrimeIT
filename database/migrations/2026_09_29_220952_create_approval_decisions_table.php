<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_decisions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('approval_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_step_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('decision', 20); // approved|rejected
            $table->string('note', 1000)->nullable();

            $table->timestamps();

            $table->unique(['approval_step_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_decisions');
    }
};