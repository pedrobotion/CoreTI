<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coreti_google_emails') || Schema::hasColumn('coreti_google_emails', 'ativo')) {
            return;
        }

        Schema::table('coreti_google_emails', function (Blueprint $table): void {
            $table->boolean('ativo')->default(true)->index()->after('status_google');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('coreti_google_emails') && Schema::hasColumn('coreti_google_emails', 'ativo')) {
            Schema::table('coreti_google_emails', function (Blueprint $table): void {
                $table->dropColumn('ativo');
            });
        }
    }
};
