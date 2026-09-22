<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coordenacao_products')) {
            Schema::create('coordenacao_products', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->unique();
                $table->string('supplier')->nullable();
                $table->text('description')->nullable();
                $table->decimal('contract_amount', 14, 2)->default(0);
                $table->string('billing_period', 30)->default('monthly')->index();
                $table->unsignedInteger('period_months')->nullable();
                $table->decimal('monthly_maintenance_amount', 14, 2)->default(0);
                $table->string('allocation_basis', 40)->default('all_collaborators')->index();
                $table->string('distribution_method', 40)->default('per_collaborator');
                $table->string('individual_appropriation_method', 40)->default('shared');
                $table->date('starts_at')->nullable();
                $table->date('ends_at')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->string('configuration_status', 30)->default('draft')->index();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('coordenacao_product_rules')) {
            Schema::create('coordenacao_product_rules', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('product_id')->constrained('coordenacao_products')->cascadeOnDelete();
                $table->string('source_type', 40);
                $table->string('source_value')->nullable();
                $table->string('include_or_exclude', 10)->default('include');
                $table->unsignedInteger('priority')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['product_id', 'source_type']);
            });
        }

        if (! Schema::hasTable('coordenacao_product_units')) {
            Schema::create('coordenacao_product_units', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('product_id')->constrained('coordenacao_products')->cascadeOnDelete();
                $table->string('unit_key');
                $table->string('participation_type', 20)->default('include');
                $table->decimal('fixed_amount', 14, 2)->nullable();
                $table->timestamps();
                $table->unique(['product_id', 'unit_key']);
            });
        }

        if (! Schema::hasTable('coordenacao_product_users')) {
            Schema::create('coordenacao_product_users', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('product_id')->constrained('coordenacao_products')->cascadeOnDelete();
                $table->string('user_key');
                $table->string('participation_type', 20)->default('exclude');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['product_id', 'user_key']);
            });
        }

        if (! Schema::hasTable('coordenacao_monthly_closings')) {
            Schema::create('coordenacao_monthly_closings', function (Blueprint $table): void {
                $table->id();
                $table->date('reference_month')->unique();
                $table->string('status', 20)->default('draft')->index();
                $table->timestamp('calculated_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamp('exported_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->decimal('total_amount', 14, 2)->default(0);
                $table->decimal('total_individual_amount', 14, 2)->default(0);
                $table->decimal('total_shared_amount', 14, 2)->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('coordenacao_monthly_allocations')) {
            Schema::create('coordenacao_monthly_allocations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('closing_id')->constrained('coordenacao_monthly_closings')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('coordenacao_products')->restrictOnDelete();
                $table->string('unit_key');
                $table->unsignedInteger('eligible_users')->default(0);
                $table->decimal('allocation_base', 14, 2)->default(0);
                $table->decimal('unit_amount', 14, 2)->default(0);
                $table->decimal('individual_amount', 14, 2)->default(0);
                $table->decimal('shared_amount', 14, 2)->default(0);
                $table->json('calculation_details')->nullable();
                $table->timestamps();
                $table->unique(['closing_id', 'product_id', 'unit_key'], 'coord_alloc_closing_product_unit_uq');
                $table->index(['closing_id', 'unit_key']);
            });
        }

        if (! Schema::hasTable('coordenacao_monthly_user_costs')) {
            Schema::create('coordenacao_monthly_user_costs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('closing_id')->constrained('coordenacao_monthly_closings')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('coordenacao_products')->restrictOnDelete();
                $table->string('user_key');
                $table->string('unit_key');
                $table->string('cost_type', 20);
                $table->decimal('amount', 14, 2)->default(0);
                $table->json('calculation_details')->nullable();
                $table->timestamps();
                $table->unique(['closing_id', 'product_id', 'user_key', 'cost_type'], 'coord_user_cost_closing_product_user_type_uq');
                $table->index(['closing_id', 'user_key']);
            });
        }

        if (! Schema::hasTable('coordenacao_pending_items')) {
            Schema::create('coordenacao_pending_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('product_id')->nullable()->constrained('coordenacao_products')->nullOnDelete();
                $table->foreignId('closing_id')->nullable()->constrained('coordenacao_monthly_closings')->nullOnDelete();
                $table->string('type', 50);
                $table->string('severity', 20)->default('warning');
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('status', 20)->default('open')->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (DB::table('coordenacao_products')->count() === 0) {
            $now = now();
            DB::table('coordenacao_products')->insert(collect([
                'Progress', 'Antivírus', 'Office', 'Office Server', 'E-mail',
                'Windows RDS', 'Citrix', 'Windows Server', 'Windows Server User',
            ])->map(fn (string $name): array => [
                'name' => $name,
                'billing_period' => 'monthly',
                'allocation_basis' => 'all_collaborators',
                'distribution_method' => 'per_collaborator',
                'individual_appropriation_method' => 'shared',
                'configuration_status' => 'draft',
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coordenacao_pending_items');
        Schema::dropIfExists('coordenacao_monthly_user_costs');
        Schema::dropIfExists('coordenacao_monthly_allocations');
        Schema::dropIfExists('coordenacao_monthly_closings');
        Schema::dropIfExists('coordenacao_product_users');
        Schema::dropIfExists('coordenacao_product_units');
        Schema::dropIfExists('coordenacao_product_rules');
        Schema::dropIfExists('coordenacao_products');
    }
};
