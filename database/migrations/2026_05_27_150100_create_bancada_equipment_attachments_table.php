<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bancada_equipment_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bancada_equipment_id')->constrained('bancada_equipments')->cascadeOnDelete();
            $table->foreignId('bancada_equipment_event_id')->nullable()->constrained('bancada_equipment_events')->nullOnDelete();
            $table->string('attachment_type', 80)->index();
            $table->string('original_name', 255);
            $table->string('storage_disk', 50)->default('local');
            $table->string('storage_path', 255);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
            $table->index(['bancada_equipment_id', 'attachment_type'], 'bq_attach_equipment_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bancada_equipment_attachments');
    }
};
