<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coreti_google_emails')) {
            return;
        }

        Schema::table('coreti_google_emails', function (Blueprint $table): void {
            if (! Schema::hasColumn('coreti_google_emails', 'centro_custo_manual')) {
                $table->boolean('centro_custo_manual')->default(false)->after('centro_custo_nome');
            }

            if (! Schema::hasColumn('coreti_google_emails', 'centro_custo_manual_at')) {
                $table->timestamp('centro_custo_manual_at')->nullable()->after('centro_custo_manual');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('coreti_google_emails')) {
            return;
        }

        Schema::table('coreti_google_emails', function (Blueprint $table): void {
            if (Schema::hasColumn('coreti_google_emails', 'centro_custo_manual_at')) {
                $table->dropColumn('centro_custo_manual_at');
            }

            if (Schema::hasColumn('coreti_google_emails', 'centro_custo_manual')) {
                $table->dropColumn('centro_custo_manual');
            }
        });
    }
};
