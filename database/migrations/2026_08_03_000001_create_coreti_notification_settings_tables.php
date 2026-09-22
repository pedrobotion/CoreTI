<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coreti_notification_recipients', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('coreti_notification_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('coreti_notification_recipient_rule', function (Blueprint $table): void {
            $table->foreignId('recipient_id')->constrained('coreti_notification_recipients')->cascadeOnDelete();
            $table->foreignId('rule_id')->constrained('coreti_notification_rules')->cascadeOnDelete();
            $table->primary(['recipient_id', 'rule_id']);
        });

        DB::table('coreti_notification_rules')->insert([
            ['event_key' => 'office_sync', 'label' => 'Sincronização Office / Microsoft 365', 'description' => 'Alterações, pendências e falhas da sincronização de licenças Office.', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['event_key' => 'google_workspace_sync', 'label' => 'Sincronização Google Workspace', 'description' => 'Alterações e falhas da sincronização de e-mails e rateio.', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['event_key' => 'cmdb_sync', 'label' => 'Sincronização CMDB / vCenter / SNMP', 'description' => 'Alterações, descobertas com falha e avisos dos conectores CMDB.', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['event_key' => 'veeam_sync', 'label' => 'Sincronização Veeam', 'description' => 'Alterações e falhas da proteção de máquinas virtuais.', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['event_key' => 'expiration_alert', 'label' => 'Vencimentos e renovações', 'description' => 'Alertas de certificados, contratos, licenças, garantias e baterias.', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['event_key' => 'system_alert', 'label' => 'Alertas do sistema', 'description' => 'Falhas gerais e avisos operacionais do CoreTI.', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('coreti_notification_recipient_rule');
        Schema::dropIfExists('coreti_notification_rules');
        Schema::dropIfExists('coreti_notification_recipients');
    }
};
