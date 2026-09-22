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
            if (! Schema::hasColumn('coreti_google_emails', 'matricula')) {
                $table->string('matricula', 50)->nullable()->index()->after('nome_usuario');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('coreti_google_emails')) {
            return;
        }

        Schema::table('coreti_google_emails', function (Blueprint $table): void {
            if (Schema::hasColumn('coreti_google_emails', 'matricula')) {
                $table->dropIndex(['matricula']);
                $table->dropColumn('matricula');
            }
        });
    }
};
