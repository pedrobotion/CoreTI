<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coordenacao_cost_rules')) {
            Schema::create('coordenacao_cost_rules', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('source_type', 50)->index();
                $table->string('match_value')->nullable();
                $table->string('billing_cycle', 20)->default('monthly')->index();
                $table->decimal('amount', 14, 2)->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->integer('sort_order')->default(0)->index();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (DB::table('coordenacao_cost_rules')->count() === 0) {
            DB::table('coordenacao_cost_rules')->insert([
                [
                    'name' => 'Office Apps',
                    'source_type' => 'office_flag',
                    'match_value' => 'office_apps',
                    'billing_cycle' => 'monthly',
                    'amount' => 0,
                    'is_active' => true,
                    'sort_order' => 10,
                    'notes' => 'Pacote Office Apps identificado pela licença Office.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'Office Business',
                    'source_type' => 'office_flag',
                    'match_value' => 'office_business',
                    'billing_cycle' => 'monthly',
                    'amount' => 0,
                    'is_active' => true,
                    'sort_order' => 20,
                    'notes' => 'Pacote Office Business identificado pela licença Office.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'Power BI Pro',
                    'source_type' => 'office_flag',
                    'match_value' => 'powerbi_pro',
                    'billing_cycle' => 'monthly',
                    'amount' => 0,
                    'is_active' => true,
                    'sort_order' => 30,
                    'notes' => 'Licença Power BI Pro.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'Power BI Premium',
                    'source_type' => 'office_flag',
                    'match_value' => 'powerbi_premium',
                    'billing_cycle' => 'monthly',
                    'amount' => 0,
                    'is_active' => true,
                    'sort_order' => 40,
                    'notes' => 'Licença Power BI Premium.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'Visio Plan',
                    'source_type' => 'office_flag',
                    'match_value' => 'visio_plan',
                    'billing_cycle' => 'monthly',
                    'amount' => 0,
                    'is_active' => true,
                    'sort_order' => 50,
                    'notes' => 'Licença Visio Plan.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'Citrix',
                    'source_type' => 'ad_group_contains',
                    'match_value' => 'Citrix',
                    'billing_cycle' => 'monthly',
                    'amount' => 0,
                    'is_active' => true,
                    'sort_order' => 60,
                    'notes' => 'Grupo AD com custo associado à camada Citrix.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'TS APP',
                    'source_type' => 'ad_group_contains',
                    'match_value' => 'TS APP',
                    'billing_cycle' => 'monthly',
                    'amount' => 0,
                    'is_active' => true,
                    'sort_order' => 70,
                    'notes' => 'Grupo AD de acesso TS.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'Progress',
                    'source_type' => 'ad_group_contains',
                    'match_value' => 'Progress',
                    'billing_cycle' => 'monthly',
                    'amount' => 0,
                    'is_active' => true,
                    'sort_order' => 80,
                    'notes' => 'Aplicações/serviços baseados em Progress.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'Qlik Sense',
                    'source_type' => 'ad_group_contains',
                    'match_value' => 'Qlik',
                    'billing_cycle' => 'monthly',
                    'amount' => 0,
                    'is_active' => false,
                    'sort_order' => 90,
                    'notes' => 'Candidato a custo futuro.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'Simpress',
                    'source_type' => 'ad_group_contains',
                    'match_value' => 'Simpress',
                    'billing_cycle' => 'monthly',
                    'amount' => 0,
                    'is_active' => false,
                    'sort_order' => 100,
                    'notes' => 'Candidato a custo futuro.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'VPN',
                    'source_type' => 'ad_group_contains',
                    'match_value' => 'VPN',
                    'billing_cycle' => 'monthly',
                    'amount' => 0,
                    'is_active' => false,
                    'sort_order' => 110,
                    'notes' => 'Candidato a custo futuro.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'Home Office',
                    'source_type' => 'ad_group_contains',
                    'match_value' => 'Home Office',
                    'billing_cycle' => 'monthly',
                    'amount' => 0,
                    'is_active' => false,
                    'sort_order' => 120,
                    'notes' => 'Candidato a custo futuro.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'RDS Remote Access Servers',
                    'source_type' => 'ad_group_contains',
                    'match_value' => 'RDS',
                    'billing_cycle' => 'monthly',
                    'amount' => 0,
                    'is_active' => false,
                    'sort_order' => 130,
                    'notes' => 'Candidato a custo futuro.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coordenacao_cost_rules');
    }
};
