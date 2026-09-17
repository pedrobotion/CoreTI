<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parque de impressoras, espelhado da EKM Insight API.
 *
 * O desenho segue os DTOs publicados na OpenAPI do portal (v1.18), para a
 * sincronização ser uma cópia direta e não uma tradução com perda:
 * DeviceRestDTO, DeviceMeterRestDTO, DeviceConsumableDetailDTO,
 * DeviceAlertRestDTO e ConsumableRequestRestDTO.
 *
 * A Insight é a fonte mestre do inventário — é a única com 100% do parque.
 * O Zabbix cobre só as 39 do Cerrado e a Sede não é monitorada em lugar
 * nenhum hoje.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printers', function (Blueprint $table): void {
            $table->id();

            // Identidade no portal: chave da sincronização.
            $table->unsignedInteger('insight_device_id')->unique();
            $table->unsignedInteger('insight_customer_id')->index();

            /*
             | Escopo derivado da unidade, como nos demais módulos. Fica nulo
             | enquanto a impressora não estiver mapeada para uma unidade —
             | melhor sem escopo e visível como pendência do que adivinhado.
             */
            $table->string('scope', 20)->nullable()->index();
            $table->unsignedBigInteger('organization_unit_id')->nullable()->index();

            $table->string('serial_number', 100)->nullable()->index();
            $table->string('asset_number', 100)->nullable()->index();
            $table->string('sticker_number', 100)->nullable();

            $table->string('manufacturer', 120)->nullable()->index();
            $table->string('model', 160)->nullable();
            $table->string('sku', 80)->nullable();
            $table->string('mib_description', 255)->nullable();
            $table->string('firmware', 120)->nullable();

            $table->string('ip_address', 45)->nullable()->index();
            $table->string('mac_address', 40)->nullable();
            $table->string('hostname', 160)->nullable();

            /*
             | monitorStatus do portal:
             |   Y = totalmente habilitada, I = só consumíveis,
             |   J = só relatórios,        X = desabilitada.
             */
            $table->string('monitor_status', 2)->nullable()->index();
            $table->string('monitor_name', 160)->nullable();

            // Onde o portal diz que ela está — o texto livre deles, que
            // alimenta o de/para com organization_units.
            $table->string('insight_location', 255)->nullable();
            $table->string('insight_zone', 160)->nullable();
            $table->unsignedInteger('insight_zone_id')->nullable();

            $table->text('comment')->nullable();
            $table->timestamp('discovery_date')->nullable();
            $table->timestamp('last_contact')->nullable()->index();
            $table->timestamp('registered')->nullable();

            $table->json('raw_data')->nullable();
            $table->timestamp('last_synced_at')->nullable()->index();
            $table->timestamps();

            $table->index(['scope', 'monitor_status']);
        });

        /*
         | Contadores de faturamento, um registro por competência.
         |
         | billingDate é a data contábil da leitura — é ela que se confere
         | contra a nota, não a data em que o coletor leu. O gestaoTI não
         | guarda histórico nenhum: sobrescreve a linha a cada sincronização,
         | e por isso não responde "quantas páginas a unidade X imprimiu em
         | julho", que é exatamente a pergunta de quem paga por página.
         */
        Schema::create('printer_meters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('printer_id')->constrained('printers')->cascadeOnDelete();
            $table->date('billing_date')->index();
            $table->timestamp('reading_date')->nullable();
            $table->timestamp('reading_date_time')->nullable();

            $table->unsignedBigInteger('a4_mono')->nullable();
            $table->unsignedBigInteger('a4_colour')->nullable();
            $table->unsignedBigInteger('engine_cycles')->nullable();
            $table->unsignedBigInteger('scans')->nullable();
            $table->unsignedBigInteger('non_copy_scans')->nullable();
            $table->unsignedBigInteger('mono_small')->nullable();
            $table->unsignedBigInteger('mono_large')->nullable();
            $table->unsignedBigInteger('colour_small')->nullable();
            $table->unsignedBigInteger('colour_large')->nullable();
            $table->unsignedBigInteger('mono_tier')->nullable();
            $table->unsignedBigInteger('colour_tier1')->nullable();
            $table->unsignedBigInteger('colour_tier2')->nullable();
            $table->unsignedBigInteger('colour_tier3')->nullable();
            $table->unsignedBigInteger('mono_pages')->nullable();
            $table->unsignedBigInteger('colour_pages')->nullable();
            $table->unsignedBigInteger('duplex')->nullable();

            $table->json('raw_data')->nullable();
            $table->timestamps();

            // Uma leitura por impressora por competência.
            $table->unique(['printer_id', 'billing_date']);
        });

        Schema::create('printer_supplies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('printer_id')->constrained('printers')->cascadeOnDelete();
            $table->unsignedInteger('insight_consumable_id')->nullable();
            $table->unsignedInteger('indice')->nullable();

            $table->string('tipo', 60)->nullable()->index();
            $table->string('cor', 40)->nullable()->index();
            $table->string('descricao', 255)->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->string('sku', 80)->nullable();
            $table->string('reorder_part', 120)->nullable();

            // O portal entrega os três: é o que permite avisar antes de acabar.
            $table->unsignedTinyInteger('percent_left')->nullable()->index();
            $table->unsignedInteger('days_left')->nullable()->index();
            $table->unsignedBigInteger('pages_left')->nullable();

            $table->unsignedInteger('rendimento')->nullable();
            $table->unsignedInteger('max_level')->nullable();
            $table->unsignedInteger('raw_level')->nullable();
            $table->unsignedInteger('days_monitored')->nullable();

            $table->timestamp('first_read')->nullable();
            $table->timestamp('last_read')->nullable()->index();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(['printer_id', 'insight_consumable_id']);
        });

        Schema::create('printer_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('printer_id')->constrained('printers')->cascadeOnDelete();
            $table->timestamp('detectado_em')->index();
            $table->timestamp('resolvido_em')->nullable()->index();
            $table->unsignedBigInteger('engine_cycles')->nullable();
            $table->unsignedTinyInteger('training_level')->nullable();
            $table->unsignedTinyInteger('severity_level')->nullable()->index();
            $table->string('alert_class', 80)->nullable()->index();
            $table->unsignedInteger('mib_code')->nullable();
            $table->string('descricao', 255)->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            // O portal reenvia o mesmo alerta a cada sincronização; sem isso
            // o histórico viraria milhares de duplicatas do mesmo evento.
            $table->unique(['printer_id', 'detectado_em', 'mib_code'], 'printer_alert_evento_unique');
        });

        Schema::create('printer_consumable_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('insight_request_id')->unique();
            $table->foreignId('printer_id')->nullable()->constrained('printers')->nullOnDelete();

            $table->string('status', 60)->nullable()->index();
            $table->timestamp('status_date')->nullable();
            $table->text('status_comment')->nullable();
            $table->timestamp('solicitado_em')->nullable()->index();
            $table->string('solicitante', 160)->nullable();
            $table->string('motivo', 255)->nullable();

            $table->unsignedTinyInteger('nivel_no_pedido')->nullable();
            $table->unsignedInteger('dias_restantes_no_pedido')->nullable();
            $table->unsignedBigInteger('contador_mono_no_pedido')->nullable();
            $table->unsignedBigInteger('contador_color_no_pedido')->nullable();
            $table->timestamp('substituido_em')->nullable();

            $table->string('consumivel_descricao', 255)->nullable();
            $table->string('consumivel_tipo', 60)->nullable();
            $table->string('consumivel_cor', 40)->nullable();

            $table->string('transportadora', 120)->nullable();
            $table->string('rastreio_codigo', 120)->nullable();
            $table->text('rastreio_url')->nullable();
            // Nosso identificador do lado do CoreTI, enviado como externalRef.
            $table->string('referencia_externa', 120)->nullable()->index();

            $table->foreignId('solicitado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->json('raw_data')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printer_consumable_requests');
        Schema::dropIfExists('printer_alerts');
        Schema::dropIfExists('printer_supplies');
        Schema::dropIfExists('printer_meters');
        Schema::dropIfExists('printers');
    }
};
