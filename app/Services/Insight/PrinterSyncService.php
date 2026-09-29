<?php

namespace App\Services\Insight;

use App\Models\OrganizationUnit;
use App\Models\Printer;
use App\Models\PrinterConsumableRequest;
use App\Models\PrinterMeter;
use App\Models\PrinterSupply;
use App\Support\ScopeRegistry;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Traz o parque de impressoras da Insight para o CoreTI.
 *
 * O portal é a fonte mestre do inventário. O que o CoreTI acrescenta é o
 * recorte Sede × Cerrado e o vínculo com a unidade organizacional — o portal
 * guarda só um texto livre de localização.
 *
 * Impressora cuja localização não casar com nenhuma unidade fica sem unidade
 * e sem escopo, listada como pendência. Adivinhar pelo texto colocaria
 * equipamento na operação errada em silêncio, e é o escopo que decide quem
 * enxerga o quê.
 */
class PrinterSyncService
{
    /** @var array<string, int>|null  nome normalizado da unidade => id */
    private ?array $unidadesPorNome = null;

    public function __construct(private readonly InsightApiClient $insight) {}

    /**
     * @param  array<int, int>  $customerIds  vazio = os configurados no .env
     * @return array<string, mixed>
     */
    public function sincronizar(array $customerIds = [], bool $dryRun = false): array
    {
        $clientes = $customerIds !== [] ? $customerIds : (array) config('insight.customer_ids', []);

        if ($clientes === []) {
            throw new InsightApiException(
                'Nenhum customerId configurado. Rode "php artisan insight:testar" para descobrir os ids e preencha INSIGHT_CUSTOMER_IDS no .env.'
            );
        }

        $resumo = ['clientes' => [], 'dry_run' => $dryRun];

        foreach ($clientes as $customerId) {
            $resumo['clientes'][$customerId] = $this->sincronizarCliente((int) $customerId, $dryRun);
        }

        return $resumo;
    }

    /** @return array<string, int> */
    private function sincronizarCliente(int $customerId, bool $dryRun): array
    {
        $contadores = ['equipamentos' => 0, 'novos' => 0, 'sem_unidade' => 0, 'suprimentos' => 0, 'contadores' => 0, 'pedidos' => 0];

        foreach ($this->insight->devices($customerId) as $device) {
            $printer = $this->gravarDevice($customerId, $device, $dryRun);
            $contadores['equipamentos']++;

            if ($printer === null) {
                continue;
            }

            if ($printer->wasRecentlyCreated) {
                $contadores['novos']++;
            }

            if ($printer->organization_unit_id === null) {
                $contadores['sem_unidade']++;
            }
        }

        if ($dryRun) {
            return $contadores;
        }

        $porDeviceId = Printer::query()
            ->where('insight_customer_id', $customerId)
            ->pluck('id', 'insight_device_id');

        $contadores['suprimentos'] = $this->sincronizarSuprimentos($customerId, $porDeviceId);
        $contadores['contadores'] = $this->sincronizarContadores($customerId, $porDeviceId);
        $contadores['pedidos'] = $this->sincronizarPedidos($customerId, $porDeviceId);

        return $contadores;
    }

    /** @param  array<string, mixed>  $device */
    private function gravarDevice(int $customerId, array $device, bool $dryRun): ?Printer
    {
        $deviceId = (int) ($device['deviceId'] ?? 0);

        if ($deviceId === 0) {
            return null;
        }

        $estendidos = (array) ($device['extendedFields'] ?? []);
        $unidadeId = $this->resolverUnidade($estendidos);

        $atributos = [
            'insight_customer_id' => $customerId,
            'organization_unit_id' => $unidadeId,
            // O escopo segue a unidade; sem unidade não há escopo a deduzir.
            'scope' => $unidadeId !== null ? $this->escopoDaUnidade($unidadeId) : null,
            'serial_number' => $this->texto($device['serialNumber'] ?? null),
            'asset_number' => $this->texto($device['assetNumber'] ?? null),
            'sticker_number' => $this->texto($device['stickerNumber'] ?? null),
            'manufacturer' => $this->texto($estendidos['manufacturer'] ?? null),
            'model' => $this->texto($estendidos['model'] ?? null),
            'sku' => $this->texto($estendidos['sku'] ?? null),
            'mib_description' => $this->texto($estendidos['mibDescription'] ?? null),
            'firmware' => $this->texto($estendidos['firmware'] ?? null),
            'ip_address' => $this->texto($device['ipAddress'] ?? null),
            'mac_address' => $this->texto($estendidos['macAddress'] ?? null),
            'hostname' => $this->texto($estendidos['hostName'] ?? null),
            'monitor_status' => $this->texto($device['monitorStatus'] ?? null),
            'monitor_name' => $this->texto($estendidos['monitorName'] ?? null),
            'insight_location' => $this->texto($estendidos['location'] ?? null),
            'insight_zone' => $this->texto($estendidos['zone'] ?? null),
            'insight_zone_id' => $estendidos['zoneId'] ?? null,
            'comment' => $this->texto($estendidos['comment'] ?? null),
            'discovery_date' => $this->data($device['discoveryDate'] ?? null),
            'last_contact' => $this->data($device['lastContact'] ?? null),
            'registered' => $this->data($device['registered'] ?? null),
            'raw_data' => $device,
            'last_synced_at' => now(),
        ];

        if ($dryRun) {
            $existente = Printer::query()->where('insight_device_id', $deviceId)->first() ?? new Printer();
            $existente->fill($atributos);

            return $existente;
        }

        return Printer::query()->updateOrCreate(['insight_device_id' => $deviceId], $atributos);
    }

    /** @param  \Illuminate\Support\Collection<int, int>  $porDeviceId */
    private function sincronizarSuprimentos(int $customerId, $porDeviceId): int
    {
        $total = 0;

        foreach ($this->insight->consumiveisDoCliente($customerId) as $registro) {
            $printerId = $porDeviceId[(int) ($registro['deviceId'] ?? 0)] ?? null;

            if ($printerId === null) {
                continue;
            }

            foreach ((array) ($registro['consumables'] ?? []) as $consumivel) {
                PrinterSupply::query()->updateOrCreate(
                    [
                        'printer_id' => $printerId,
                        'insight_consumable_id' => $consumivel['consumableId'] ?? null,
                    ],
                    [
                        'indice' => $consumivel['index'] ?? null,
                        'tipo' => $this->texto($consumivel['type'] ?? null),
                        'cor' => $this->texto($consumivel['colour'] ?? null),
                        'descricao' => $this->texto($consumivel['description'] ?? null),
                        'serial_number' => $this->texto($consumivel['serialNumber'] ?? null),
                        'sku' => $this->texto($consumivel['sku'] ?? null),
                        'reorder_part' => $this->texto(Arr::get($consumivel, 'reorderPart.description')),
                        'percent_left' => $this->inteiro($consumivel['percentLeft'] ?? null, 0, 100),
                        'days_left' => $this->inteiro($consumivel['daysLeft'] ?? null),
                        'pages_left' => $this->inteiro($consumivel['pagesLeft'] ?? null),
                        'rendimento' => $this->inteiro($consumivel['yield'] ?? null),
                        'max_level' => $this->inteiro($consumivel['maxLevel'] ?? null),
                        'raw_level' => $this->inteiro($consumivel['rawLevel'] ?? null),
                        'days_monitored' => $this->inteiro($consumivel['daysMonitored'] ?? null),
                        'first_read' => $this->data($consumivel['firstRead'] ?? null),
                        'last_read' => $this->data($consumivel['lastRead'] ?? null),
                        'raw_data' => $consumivel,
                    ]
                );

                $total++;
            }
        }

        return $total;
    }

    /** @param  \Illuminate\Support\Collection<int, int>  $porDeviceId */
    private function sincronizarContadores(int $customerId, $porDeviceId): int
    {
        $total = 0;

        foreach ($this->insight->contadores($customerId) as $leitura) {
            $printerId = $porDeviceId[(int) ($leitura['deviceId'] ?? 0)] ?? null;
            $billingDate = $this->data($leitura['billingDate'] ?? null);

            if ($printerId === null || $billingDate === null) {
                continue;
            }

            PrinterMeter::query()->updateOrCreate(
                ['printer_id' => $printerId, 'billing_date' => $billingDate->toDateString()],
                [
                    'reading_date' => $this->data($leitura['readingDate'] ?? null),
                    'reading_date_time' => $this->data($leitura['readingDateTime'] ?? null),
                    'a4_mono' => $this->inteiro($leitura['a4Mono'] ?? null),
                    'a4_colour' => $this->inteiro($leitura['a4Colour'] ?? null),
                    'engine_cycles' => $this->inteiro($leitura['engineCycles'] ?? null),
                    'scans' => $this->inteiro($leitura['scans'] ?? null),
                    'non_copy_scans' => $this->inteiro($leitura['nonCopyScans'] ?? null),
                    'mono_small' => $this->inteiro($leitura['monoSmall'] ?? null),
                    'mono_large' => $this->inteiro($leitura['monoLarge'] ?? null),
                    'colour_small' => $this->inteiro($leitura['colourSmall'] ?? null),
                    'colour_large' => $this->inteiro($leitura['colourLarge'] ?? null),
                    'mono_tier' => $this->inteiro($leitura['monoTier'] ?? null),
                    'colour_tier1' => $this->inteiro($leitura['colourTier1'] ?? null),
                    'colour_tier2' => $this->inteiro($leitura['colourTier2'] ?? null),
                    'colour_tier3' => $this->inteiro($leitura['colourTier3'] ?? null),
                    'mono_pages' => $this->inteiro($leitura['monoPages'] ?? null),
                    'colour_pages' => $this->inteiro($leitura['colourPages'] ?? null),
                    'duplex' => $this->inteiro($leitura['duplex'] ?? null),
                    'raw_data' => $leitura,
                ]
            );

            $total++;
        }

        return $total;
    }

    /** @param  \Illuminate\Support\Collection<int, int>  $porDeviceId */
    private function sincronizarPedidos(int $customerId, $porDeviceId): int
    {
        $total = 0;

        foreach ($this->insight->pedidosDeConsumivel(['customerId' => $customerId]) as $pedido) {
            $id = (int) ($pedido['id'] ?? 0);

            if ($id === 0) {
                continue;
            }

            PrinterConsumableRequest::query()->updateOrCreate(
                ['insight_request_id' => $id],
                [
                    'printer_id' => $porDeviceId[(int) ($pedido['deviceId'] ?? 0)] ?? null,
                    'status' => $this->texto($pedido['status'] ?? null),
                    'status_date' => $this->data($pedido['statusDate'] ?? null),
                    'status_comment' => $this->texto($pedido['statusComment'] ?? null),
                    'solicitado_em' => $this->data($pedido['requested'] ?? null),
                    'solicitante' => $this->texto($pedido['creator'] ?? null),
                    'motivo' => $this->texto($pedido['reason'] ?? null),
                    'nivel_no_pedido' => $this->inteiro($pedido['requestedLevel'] ?? null, 0, 100),
                    'dias_restantes_no_pedido' => $this->inteiro($pedido['requestedDaysLeft'] ?? null),
                    'contador_mono_no_pedido' => $this->inteiro($pedido['requestedMonoPageCount'] ?? null),
                    'contador_color_no_pedido' => $this->inteiro($pedido['requestedColourPageCount'] ?? null),
                    'substituido_em' => $this->data($pedido['replacedDate'] ?? null),
                    'consumivel_descricao' => $this->texto(Arr::get($pedido, 'consumable.description')),
                    'consumivel_tipo' => $this->texto(Arr::get($pedido, 'consumable.type')),
                    'consumivel_cor' => $this->texto(Arr::get($pedido, 'consumable.colour')),
                    'transportadora' => $this->texto($pedido['courierName'] ?? null),
                    'rastreio_codigo' => $this->texto($pedido['trackingId'] ?? null),
                    'rastreio_url' => $this->texto($pedido['trackingUrl'] ?? null),
                    'referencia_externa' => $this->texto($pedido['externalRef'] ?? null),
                    'raw_data' => $pedido,
                    'last_synced_at' => now(),
                ]
            );

            $total++;
        }

        return $total;
    }

    // ------------------------------------------------------------------
    // Apoio
    // ------------------------------------------------------------------

    /**
     * Casa a localização do portal com uma unidade da intranet.
     *
     * Comparação sem acento e sem caixa, como na importação do gestaoTI. Sem
     * correspondência devolve null — pendência é melhor que palpite.
     *
     * @param  array<string, mixed>  $estendidos
     */
    private function resolverUnidade(array $estendidos): ?int
    {
        foreach ([$estendidos['location'] ?? null, $estendidos['zone'] ?? null] as $candidato) {
            $chave = $this->normalizar((string) $candidato);

            if ($chave !== '' && isset($this->unidades()[$chave])) {
                return $this->unidades()[$chave];
            }
        }

        return null;
    }

    /** @return array<string, int> */
    private function unidades(): array
    {
        return $this->unidadesPorNome ??= OrganizationUnit::query()
            ->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $nome) => [$this->normalizar((string) $nome) => (int) $id])
            ->all();
    }

    private function escopoDaUnidade(int $unidadeId): ?string
    {
        $sourceId = OrganizationUnit::query()->whereKey($unidadeId)->value('source_id');

        return $sourceId === null ? null : ScopeRegistry::forUnit((string) $sourceId);
    }

    private function normalizar(string $valor): string
    {
        return preg_replace('/\s+/', ' ', trim(mb_strtolower(Str::ascii($valor)))) ?? '';
    }

    private function texto(mixed $valor): ?string
    {
        $texto = trim((string) ($valor ?? ''));

        return $texto === '' ? null : $texto;
    }

    private function data(mixed $valor): ?Carbon
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $valor);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Número da API, ou null quando não é número de verdade.
     *
     * O portal usa -1 e -2 como "desconhecido" — aparece em daysLeft e
     * pagesLeft de consumível recém-instalado, que ainda não tem histórico
     * para projetar. Não é quantidade negativa, é ausência de leitura.
     *
     * Por isso o mínimo é ZERO por padrão, e não "sem mínimo". Tudo que
     * mapeamos aqui é quantidade — dias, páginas, ciclos, níveis, scans — e
     * as 38 colunas correspondentes são unsigned. Deixar o -1 passar rendia
     * "Out of range value for column 'days_left'" e derrubava a
     * sincronização inteira por causa de um toner novo.
     */
    private function inteiro(mixed $valor, ?int $min = 0, ?int $max = null): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $inteiro = (int) $valor;

        if ($min !== null && $inteiro < $min) {
            return null;
        }

        return $max !== null ? min($inteiro, $max) : $inteiro;
    }
}
