<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cmdb_assets', function (Blueprint $table): void {
            $table->string('source_type', 30)->nullable()->index()->after('notes');
            $table->foreignId('source_connector_id')->nullable()->index()->after('source_type');
            $table->string('source_external_id')->nullable()->index()->after('source_connector_id');
            $table->timestamp('last_synced_at')->nullable()->index()->after('source_external_id');
            $table->string('sync_status', 30)->nullable()->index()->after('last_synced_at');
            $table->text('sync_error')->nullable()->after('sync_status');
            $table->string('sync_hash', 64)->nullable()->index()->after('sync_error');
        });
    }

    public function down(): void
    {
        Schema::table('cmdb_assets', function (Blueprint $table): void {
            $table->dropColumn([
                'source_type',
                'source_connector_id',
                'source_external_id',
                'last_synced_at',
                'sync_status',
                'sync_error',
                'sync_hash',
            ]);
        });
    }
};
