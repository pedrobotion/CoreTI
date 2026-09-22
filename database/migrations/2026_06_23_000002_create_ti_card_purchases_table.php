<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ti_card_purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ti_card_id')->constrained('ti_cards')->cascadeOnDelete();
            $table->string('purchase_name');
            $table->decimal('purchase_value', 10, 2);
            $table->date('purchase_date')->nullable();
            $table->text('observacao')->nullable();
            $table->string('invoice_original_name')->nullable();
            $table->string('invoice_storage_path')->nullable();
            $table->string('invoice_mime_type')->nullable();
            $table->timestamp('invoice_uploaded_at')->nullable();
            $table->foreignId('invoice_uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['ti_card_id', 'purchase_date']);
            $table->index('purchase_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ti_card_purchases');
    }
};
