<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cmdb_asset_histories')) {
            Schema::create('cmdb_asset_histories', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('asset_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('event_type', 80)->index();
                $table->string('title');
                $table->text('description')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cmdb_asset_documents')) {
            Schema::create('cmdb_asset_documents', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('asset_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('document_type', 50)->index();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('file_path');
                $table->string('original_name');
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cmdb_asset_documents');
        Schema::dropIfExists('cmdb_asset_histories');
    }
};
