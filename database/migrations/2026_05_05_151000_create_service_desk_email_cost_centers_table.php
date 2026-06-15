<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_desk_email_cost_centers', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 20)->index();
            $table->string('name');
            $table->string('unicoop', 50)->nullable()->index();
            $table->string('area', 50)->nullable()->index();
            $table->string('source_table', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamps();

            $table->unique(['scope', 'name']);
            $table->index(['source_table', 'source_id']);
        });

        Schema::table('service_desk_emails', function (Blueprint $table) {
            $table->foreignId('service_desk_email_cost_center_id')
                ->nullable()
                ->after('id_pessoa')
                ->constrained('service_desk_email_cost_centers')
                ->nullOnDelete();
            $table->string('legacy_source_table', 100)->nullable()->after('observacao');
            $table->unsignedBigInteger('legacy_source_id')->nullable()->after('legacy_source_table');
            $table->boolean('legacy_excluido')->nullable()->after('legacy_source_id');

            $table->index(['legacy_source_table', 'legacy_source_id'], 'sd_emails_legacy_source_index');
        });

        DB::table('service_desk_emails')
            ->whereNotNull('centro_custo')
            ->orderBy('id')
            ->chunkById(200, function ($emails): void {
                foreach ($emails as $email) {
                    DB::table('service_desk_email_cost_centers')->updateOrInsert(
                        [
                            'scope' => $email->scope,
                            'name' => $email->centro_custo,
                        ],
                        [
                            'unicoop' => $email->unicoop_sede,
                            'area' => $email->area_sede,
                            'source_table' => 'service_desk_emails',
                            'source_id' => $email->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    );

                    $costCenterId = DB::table('service_desk_email_cost_centers')
                        ->where('scope', $email->scope)
                        ->where('name', $email->centro_custo)
                        ->value('id');

                    DB::table('service_desk_emails')
                        ->where('id', $email->id)
                        ->update(['service_desk_email_cost_center_id' => $costCenterId]);
                }
            }, 'id');
    }

    public function down(): void
    {
        Schema::table('service_desk_emails', function (Blueprint $table) {
            $table->dropIndex('sd_emails_legacy_source_index');
            $table->dropColumn(['legacy_source_table', 'legacy_source_id', 'legacy_excluido']);
            $table->dropConstrainedForeignId('service_desk_email_cost_center_id');
        });

        Schema::dropIfExists('service_desk_email_cost_centers');
    }
};
