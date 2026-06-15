<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bancada_equipment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bancada_equipment_id')->constrained('bancada_equipments')->cascadeOnDelete();
            $table->string('previous_status', 60)->nullable()->index();
            $table->string('new_status', 60)->nullable()->index();
            $table->string('action', 120)->index();
            $table->string('module', 30)->default('Sistema')->index();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observation')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['bancada_equipment_id', 'created_at'], 'bq_events_equipment_created_idx');
        });

        // Backfill inicial seguro para manter trilha mínima sem alterar fluxo atual.
        $now = now();

        $equipmentRows = DB::table('bancada_equipments')
            ->select('id', 'status', 'created_at', 'updated_at')
            ->orderBy('id')
            ->get();

        $batch = [];
        foreach ($equipmentRows as $row) {
            $batch[] = [
                'bancada_equipment_id' => $row->id,
                'previous_status' => null,
                'new_status' => $row->status,
                'action' => 'snapshot_inicial',
                'module' => 'Sistema',
                'performed_by' => null,
                'observation' => 'Evento inicial gerado automaticamente na migração.',
                'metadata' => json_encode([
                    'source' => 'migration_backfill',
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => $row->created_at ?? $now,
                'updated_at' => $row->updated_at ?? $now,
            ];

            if (count($batch) >= 500) {
                DB::table('bancada_equipment_events')->insert($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            DB::table('bancada_equipment_events')->insert($batch);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bancada_equipment_events');
    }
};
