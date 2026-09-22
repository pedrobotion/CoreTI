<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registra o expurgo do AD entre os eventos de notificação do CoreTI.
 *
 * Os destinatários passam a ser mantidos na tela de configurações, junto com
 * as demais automações, em vez de uma variável no .env. Uma lista em arquivo
 * de ambiente só muda com deploy e não fica visível para quem opera.
 */
return new class extends Migration
{
    private const CHAVE = 'ad_purge';

    public function up(): void
    {
        if (! Schema::hasTable('coreti_notification_rules')) {
            return;
        }

        $existe = DB::table('coreti_notification_rules')->where('event_key', self::CHAVE)->exists();

        if ($existe) {
            return;
        }

        DB::table('coreti_notification_rules')->insert([
            'event_key' => self::CHAVE,
            'label' => 'Expurgo do Active Directory',
            'description' => 'Aviso de lote mensal de contas desativadas aguardando aprovação.',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('coreti_notification_rules')) {
            return;
        }

        // Remove os vínculos antes da regra para não deixar órfãos na pivô.
        $id = DB::table('coreti_notification_rules')->where('event_key', self::CHAVE)->value('id');

        if ($id !== null) {
            DB::table('coreti_notification_recipient_rule')->where('rule_id', $id)->delete();
            DB::table('coreti_notification_rules')->where('id', $id)->delete();
        }
    }
};
