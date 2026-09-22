<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_license_prices', function (Blueprint $table): void {
            $table->id();
            $table->string('sku_id', 100)->index();
            $table->string('sku_name');
            $table->decimal('monthly_price', 12, 2)->default(0);
            $table->string('currency', 10)->default('BRL')->index();
            $table->date('valid_from')->index();
            $table->date('valid_until')->nullable()->index();
            $table->boolean('active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();

            $table->index(['sku_id', 'active', 'valid_from', 'valid_until'], 'office_price_lookup_idx');
        });

        Schema::create('office_allocation_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('competence', 7)->index();
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 30)->default('draft')->index();
            $table->unsignedInteger('users_count')->default(0);
            $table->unsignedInteger('assignments_count')->default(0);
            $table->unsignedInteger('classified_assignments_count')->default(0);
            $table->unsignedInteger('pending_assignments_count')->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->decimal('annual_estimated_amount', 14, 2)->default(0);
            $table->unsignedBigInteger('generated_by')->nullable()->index();
            $table->timestamp('generated_at')->nullable();
            $table->unsignedBigInteger('validated_by')->nullable()->index();
            $table->timestamp('validated_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable()->index();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable()->index();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->json('price_snapshot')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();

            $table->unique(['competence', 'version'], 'office_allocation_competence_version_unique');
            $table->index(['competence', 'status'], 'office_allocation_competence_status_idx');
        });

        Schema::create('office_allocation_rows', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('batch_id')->index();
            $table->unsignedBigInteger('office_license_id')->nullable()->index();
            $table->string('office_user_external_id')->nullable()->index();
            $table->string('user_name');
            $table->string('user_email')->index();
            $table->string('employee_source_id', 100)->nullable()->index();
            $table->string('employee_number', 100)->nullable()->index();
            $table->string('sku_id', 100)->index();
            $table->string('sku_name');
            $table->unsignedBigInteger('organization_unit_id')->nullable()->index();
            $table->unsignedBigInteger('organization_department_id')->nullable()->index();
            $table->unsignedBigInteger('organization_cost_center_id')->nullable()->index();
            $table->string('cost_center_source_id', 100)->nullable()->index();
            $table->string('unit_name_snapshot')->nullable();
            $table->string('department_name_snapshot')->nullable();
            $table->string('cost_center_code_snapshot', 100)->nullable();
            $table->string('cost_center_name_snapshot')->nullable();
            $table->string('classification_type', 50)->index();
            $table->string('classification_status', 80)->index();
            $table->text('classification_message')->nullable();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['batch_id', 'office_license_id', 'sku_id'], 'office_allocation_row_assignment_unique');
            $table->index(['batch_id', 'classification_status'], 'office_allocation_row_status_idx');
            $table->index(['batch_id', 'organization_unit_id', 'organization_department_id'], 'office_allocation_row_org_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_allocation_rows');
        Schema::dropIfExists('office_allocation_batches');
        Schema::dropIfExists('office_license_prices');
    }
};
