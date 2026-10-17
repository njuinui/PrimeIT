<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A design "version" is the design-world equivalent of a commit.
        Schema::create('design_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('design_file_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('label', 120);
            $table->text('note')->nullable();

            $table->unsignedTinyInteger('progress')->nullable();
            $table->string('status', 24)->nullable();

            // Filled by the Figma sync; manual (Adobe XD etc.) versions leave these null.
            $table->string('external_version_id')->nullable();
            $table->string('external_author')->nullable();
            $table->text('thumbnail_url')->nullable();

            $table->timestamp('committed_at');
            $table->timestamps();

            $table->unique(['design_file_id', 'external_version_id']);
            $table->index(['design_file_id', 'committed_at']);
            $table->index(['user_id', 'committed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_versions');
    }
};
