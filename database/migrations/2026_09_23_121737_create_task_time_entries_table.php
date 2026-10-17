<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_time_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('minutes');
            $table->string('note', 500)->nullable();
            $table->date('worked_at')->nullable();

            $table->timestamps();

            $table->index(['task_id', 'worked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_time_entries');
    }
};