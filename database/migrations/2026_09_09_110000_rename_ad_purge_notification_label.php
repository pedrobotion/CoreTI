<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Renomeia o evento de notificação: "Expurgo" soa agressivo para quem lê o
 * assunto do e-mail e para quem opera a tela.
 *
 * Só o rótulo muda. A event_key continua 'ad_purge' porque é referenciada em
 * código e nos vínculos já cadastrados de destinatários — trocá-la desfaria a
 * configuração que o time acabou de fazer.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coreti_notification_rules')) {
            return;
        }

        DB::table('coreti_notification_rules')
            ->where('event_key', 'ad_purge')
            ->update([
                'label' => 'Exclusão de contas do Active Directory',
                'description' => 'Aviso de lote mensal de contas desativadas aguardando aprovação.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('coreti_notification_rules')) {
            return;
        }

        DB::table('coreti_notification_rules')
            ->where('event_key', 'ad_purge')
            ->update(['label' => 'Expurgo do Active Directory', 'updated_at' => now()]);
    }
};
