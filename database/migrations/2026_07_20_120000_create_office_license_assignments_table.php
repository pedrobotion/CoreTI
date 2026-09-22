<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_license_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('office_license_id')->constrained('office_licenses')->cascadeOnDelete();
            $table->string('microsoft_user_id', 100)->nullable()->index();
            $table->string('microsoft_upn')->nullable()->index();
            $table->string('user_name');
            $table->string('user_email')->index();
            // Aceita tanto o GUID do Graph quanto identificadores legados.
            $table->string('sku_id', 100);
            $table->string('sku_part_number', 150)->nullable();
            $table->string('sku_name', 255)->nullable();
            $table->string('license_category', 80)->nullable()->index();
            $table->boolean('account_enabled')->default(true);
            $table->string('license_source', 50)->default('microsoft_graph');
            $table->json('metadata')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['office_license_id', 'sku_id'], 'office_assignment_license_sku_unique');
            $table->index(['user_email', 'sku_part_number'], 'office_assignment_user_sku_idx');
        });

        if (Schema::hasTable('office_licenses')) {
            DB::table('office_licenses')->select([
                'id', 'microsoft_user_id', 'microsoft_upn', 'nome', 'email',
                'account_enabled', 'license_skus', 'license_source',
            ])->orderBy('id')->chunkById(500, function ($licenses): void {
                $rows = [];
                foreach ($licenses as $license) {
                    $skus = json_decode((string) ($license->license_skus ?? '[]'), true);
                    foreach (is_array($skus) ? $skus : [] as $sku) {
                        $skuId = trim((string) (is_array($sku) ? ($sku['sku_id'] ?? '') : $sku));
                        if ($skuId === '') {
                            continue;
                        }

                        $skuPart = trim((string) (is_array($sku) ? ($sku['sku_part_number'] ?? '') : ''));
                        $rows[] = [
                            'office_license_id' => $license->id,
                            'microsoft_user_id' => $license->microsoft_user_id,
                            'microsoft_upn' => $license->microsoft_upn,
                            'user_name' => $license->nome,
                            'user_email' => $license->email,
                            'sku_id' => $skuId,
                            'sku_part_number' => $skuPart !== '' ? $skuPart : null,
                            'sku_name' => $skuPart !== '' ? $skuPart : $skuId,
                            'license_category' => is_array($sku) ? ($sku['mapped_category'] ?? null) : null,
                            'account_enabled' => (bool) $license->account_enabled,
                            'license_source' => $license->license_source ?: 'microsoft_graph',
                            'metadata' => json_encode(['migration_backfill' => true], JSON_UNESCAPED_UNICODE),
                            'synced_at' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('office_license_assignments')->insertOrIgnore($chunk);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('office_license_assignments');
    }
};
