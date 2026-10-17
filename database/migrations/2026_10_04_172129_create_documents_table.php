<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('folder_id')->nullable()->constrained('document_folders')->nullOnDelete();

            $table->string('title', 200);
            $table->text('description')->nullable();

            $table->string('file_name', 200);
            $table->string('file_path')->nullable();      // current version's path
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);

            $table->unsignedSmallInteger('current_version')->default(1);

            $table->string('status', 20)->default('active'); // active|locked|archived
            $table->json('tags')->nullable();

            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('employees')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['folder_id', 'status']);
            $table->index('department_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};