<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            // One reminder per user × remindable × type — forever.
            // Makes the scanner idempotent even under concurrent runs.
            $table->unique(
                ['user_id', 'remindable_type', 'remindable_id', 'type'],
                'reminders_user_remindable_type_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->dropUnique('reminders_user_remindable_type_unique');
        });
    }
};