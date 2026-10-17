<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('title', 200);
            $table->text('description')->nullable();       // agenda

            $table->string('location', 255)->nullable();
            $table->string('video_link', 500)->nullable();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            $table->string('status', 20)->default('scheduled'); // scheduled|completed|cancelled

            $table->foreignId('organized_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};