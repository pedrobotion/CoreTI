<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Isolamento entre Sede (PR) e Cerrado (GO/MG).
 *
 * O gestaoTI resolvia isso com tenant_id e um global scope que filtrava tudo
 * em silêncio. Aqui o escopo é uma coluna comum e o filtro é explícito na
 * query, no mesmo espírito do 'scope' que os e-mails do ServiceDesk já usam:
 * quem lê o código enxerga o recorte, em vez de depender de um scope global.
 *
 * unit_scopes é o de/para unidade → escopo, editável em tela. A classificação
 * inicial segue a geografia das unidades, conferida contra a localização real
 * dos equipamentos do gestaoTI: os 280 do Cerrado estão todos em unidade
 * GO/MG e os 9 da Sede todos em unidade PR — nenhum fora do recorte.
 *
 * Unidade que a intranet criar depois NÃO entra aqui sozinha: fica fora da
 * tabela, cai na lista de pendências e espera classificação humana. Adivinhar
 * pela UF classificaria errado uma unidade sem CEP (a 85 é assim hoje) sem
 * ninguém perceber.
 */
return new class extends Migration
{
    /**
     * As 18 unidades do Cerrado (GO/MG), por source_id da intranet.
     *
     * As quatro últimas não têm equipamento no gestaoTI e foram confirmadas
     * caso a caso — ficam anotadas para quem reabrir isso não precisar
     * refazer a conferência.
     */
    private const CERRADO_UNITS = [
        '59',     // Cristalina II
        '16',     // Cristalina (a "Cristalina I" do gestaoTI)
        '72',     // Coromandel (MG)
        '55',     // Silvânia
        '53',     // Santo Antonio do Rio Verde
        '19',     // Campo Alegre de Goiás
        '60',     // Ipameri
        '56',     // Guarda-Mor (MG)
        '58',     // São João D'Aliança
        '78',     // Piracanjuba
        '79',     // Patos de Minas (MG)
        '77',     // Catalão
        '57',     // UBC - Unidade de Beneficiamento de Café - Cerrado
        '70',     // Orizona
        '63',     // Paracatu (MG) — sem equipamento; confirmado Cerrado
        '82',     // Planaltina — unidade nova, não existia no gestaoTI
        '999999', // Escritório Comercial em Catalão — idem
        '85',     // Fabrica de Ração - GO — sem CEP; não confundir com a 15 (Mandaguari/PR)
    ];

    public function up(): void
    {
        Schema::create('unit_scopes', function (Blueprint $table): void {
            $table->id();
            $table->string('organization_unit_source_id', 100)->unique();
            $table->string('scope', 20)->index();
            $table->text('notes')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('user_scopes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 20);
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'scope']);
            $table->index('scope');
        });

        $this->classifyUnits();
        $this->grantScopesToAdmins();
    }

    public function down(): void
    {
        Schema::dropIfExists('user_scopes');
        Schema::dropIfExists('unit_scopes');
    }

    private function classifyUnits(): void
    {
        if (! Schema::hasTable('organization_units')) {
            return;
        }

        $now = now();
        $cerrado = array_flip(self::CERRADO_UNITS);
        $rows = [];

        foreach (DB::table('organization_units')->pluck('source_id') as $sourceId) {
            $sourceId = (string) $sourceId;

            if ($sourceId === '') {
                continue;
            }

            $rows[$sourceId] = [
                'organization_unit_source_id' => $sourceId,
                'scope' => isset($cerrado[$sourceId]) ? 'cerrado' : 'sede',
                'notes' => null,
                'assigned_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Uma unidade do Cerrado que ainda não tenha chegado pela sincronização
        // continua classificada: o de/para não pode depender da ordem do sync.
        foreach (self::CERRADO_UNITS as $sourceId) {
            $rows[$sourceId] ??= [
                'organization_unit_source_id' => $sourceId,
                'scope' => 'cerrado',
                'notes' => 'Classificada na carga inicial; unidade ainda não presente em organization_units.',
                'assigned_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk(array_values($rows), 200) as $chunk) {
            DB::table('unit_scopes')->insert($chunk);
        }
    }

    /**
     * Mesma lógica do backfill de user_module_accesses: quem já administra o
     * sistema recebe concessão explícita, para que o bypass de admin possa ser
     * desligado depois sem trancar ninguém para fora.
     */
    private function grantScopesToAdmins(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'role')) {
            return;
        }

        $now = now();
        $rows = [];

        foreach (DB::table('users')->where('role', 'admin')->pluck('id') as $userId) {
            foreach (array_keys((array) config('scopes.scopes', [])) as $scope) {
                $rows[] = [
                    'user_id' => (int) $userId,
                    'scope' => $scope,
                    'granted_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('user_scopes')->insert($chunk);
        }
    }
};
