<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category', 80)->nullable()->index();
            $table->string('file_name');
            $table->string('file_extension', 20)->nullable()->index();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('file_path');
            $table->string('image_path')->nullable();
            $table->string('source_path')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('downloads_count')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
