<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_risks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            $table->string('impact', 20)->default('medium');      // low|medium|high|critical
            $table->string('likelihood', 20)->default('medium');  // low|medium|high
            $table->string('status', 20)->default('open');        // open|mitigating|closed

            $table->text('mitigation')->nullable();

            $table->foreignId('owner_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('review_date')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_risks');
    }
};