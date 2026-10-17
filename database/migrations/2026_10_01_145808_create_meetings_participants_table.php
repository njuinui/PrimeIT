<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_participants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            $table->string('attendance', 20)->default('required'); // required|optional
            $table->string('response', 20)->default('waiting');    // waiting|going|declined|maybe

            $table->timestamp('responded_at')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['meeting_id', 'employee_id']);
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_participants');
    }
};