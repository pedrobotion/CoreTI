<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cmdb_discovered_assets')) {
            return;
        }

        Schema::table('cmdb_discovered_assets', function (Blueprint $table): void {
            if (! Schema::hasColumn('cmdb_discovered_assets', 'object_type')) $table->string('object_type', 50)->nullable()->after('source_type')->index();
            if (! Schema::hasColumn('cmdb_discovered_assets', 'content_hash')) $table->string('content_hash', 64)->nullable()->after('source_hash')->index();
            if (! Schema::hasColumn('cmdb_discovered_assets', 'last_seen_at')) $table->timestamp('last_seen_at')->nullable()->after('discovered_at')->index();
            if (! Schema::hasColumn('cmdb_discovered_assets', 'source_missing_at')) $table->timestamp('source_missing_at')->nullable()->after('last_seen_at')->index();
            if (! Schema::hasColumn('cmdb_discovered_assets', 'missing_count')) $table->unsignedInteger('missing_count')->default(0)->after('source_missing_at');
            if (! Schema::hasColumn('cmdb_discovered_assets', 'match_score')) $table->unsignedInteger('match_score')->nullable()->after('missing_count');
            if (! Schema::hasColumn('cmdb_discovered_assets', 'match_reason')) $table->string('match_reason')->nullable()->after('match_score');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cmdb_discovered_assets')) return;
        Schema::table('cmdb_discovered_assets', function (Blueprint $table): void {
            foreach (['object_type', 'content_hash', 'last_seen_at', 'source_missing_at', 'missing_count', 'match_score', 'match_reason'] as $column) {
                if (Schema::hasColumn('cmdb_discovered_assets', $column)) $table->dropColumn($column);
            }
        });
    }
};
