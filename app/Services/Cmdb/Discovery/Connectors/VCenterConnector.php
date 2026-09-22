<?php

namespace App\Services\Cmdb\Discovery\Connectors;

use App\Models\CmdbConnector;
use App\Services\Cmdb\Discovery\VCenterGuestNetworkCollector;
use App\Services\Cmdb\Discovery\VCenterPayloadExtractor;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class VCenterConnector extends AbstractConnector
{
    private const DEFAULT_OBJECT_TYPES = ['datacenter', 'cluster', 'host', 'virtual_machine', 'datastore', 'network'];

    public function testConnection(CmdbConnector $connector): array
    {
        $startedAt = microtime(true);
        $client = new VCenterApiClient($connector);

        try {
            $about = $client->about();
            $counts = [];
            foreach (self::DEFAULT_OBJECT_TYPES as $type) {
                $counts[$type] = count($client->list($this->resourceFor($type)));
            }

            return [
                'success' => true,
                'status' => 'success',
                'source' => 'vcenter',
                'message' => 'Conexão com o vCenter estabelecida com sucesso.',
                'data' => [
                    'version' => Arr::get($about, 'version'),
                    'build' => Arr::get($about, 'build'),
                    'full_name' => Arr::get($about, 'full_name'),
                    'counts' => $counts,
                    'response_time_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'tls_verified' => (bool) ($connector->verify_ssl ?? true),
                ],
                'items' => [],
                'warnings' => ($connector->verify_ssl ?? true) ? [] : ['A validação TLS está desativada.'],
                'meta' => ['connector_type' => 'vcenter'],
            ];
        } catch (VCenterApiException $exception) {
            return $this->failure($exception, $startedAt);
        } finally {
            $client->close();
        }
    }

    public function discover(CmdbConnector $connector): array
    {
        $client = new VCenterApiClient($connector);
        $items = [];
        $warnings = [];
        $startedAt = microtime(true);

        try {
            $about = $client->about();
            $types = $this->objectTypes($connector);
            $limit = max(0, (int) Arr::get($connector->settings ?? [], 'runtime_limit', 0));

            foreach ($types as $type) {
                try {
                    foreach ($client->list($this->resourceFor($type)) as $object) {
                        $object = (array) $object;
                        if (! $this->passesFilter($connector, $type, $object)) {
                            continue;
                        }
                        if ($type === 'virtual_machine' && ! ($connector->import_powered_off ?? true) && strtoupper((string) ($object['power_state'] ?? '')) === 'POWERED_OFF') {
                            continue;
                        }
                        $items[] = $this->normalizeObject($connector, $client, $type, $object);
                        if ($limit > 0 && count($items) >= $limit) {
                            break 2;
                        }
                    }
                } catch (VCenterApiException $exception) {
                    $warnings[] = ucfirst($type) . ': ' . $exception->getMessage();
                }
            }

            return [
                'success' => true,
                'status' => $warnings === [] ? 'success' : 'warning',
                'source' => 'vcenter',
                'message' => 'Inventário do vCenter descoberto para revisão no CMDB.',
                'data' => [
                    'version' => Arr::get($about, 'version'),
                    'build' => Arr::get($about, 'build'),
                    'object_types' => $types,
                    'response_time_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ],
                'items' => $items,
                'warnings' => $warnings,
                'meta' => [
                    'connector_type' => 'vcenter',
                    'complete' => $warnings === [] && $this->isCompleteRun($connector, $types),
                    'objects_received' => count($items),
                ],
            ];
        } catch (VCenterApiException $exception) {
            return $this->failure($exception, $startedAt);
        } finally {
            $client->close();
        }
    }

    public function sync(CmdbConnector $connector): array
    {
        return $this->discover($connector);
    }

    public function __construct(private readonly VCenterGuestNetworkCollector $guestNetwork = new VCenterGuestNetworkCollector())
    {
    }

    /**
     * Coleta de IP durante o inventário — DESLIGADA por padrão.
     *
     * Cada VM ligada custa duas chamadas extras, e o vCenter precisa consultar
     * o VMware Tools dentro da máquina. Medido neste ambiente: o inventário
     * passou de 12s para 45s por ciclo, de hora em hora, sem ganho real —
     * endereço IP de VM raramente muda.
     *
     * O enriquecimento passou para o comando cmdb:enriquecer-rede, agendado em
     * cadência própria. Quem quiser o comportamento antigo pode religar com
     * 'collect_guest_network' => true nas settings do conector.
     */
    private function coletaRedeHabilitada(CmdbConnector $connector): bool
    {
        return (bool) Arr::get($connector->settings ?? [], 'collect_guest_network', false);
    }

    private function normalizeObject(CmdbConnector $connector, VCenterApiClient $client, string $type, array $object): array
    {
        $externalId = (string) ($object[$this->idKeyFor($type)] ?? $object['id'] ?? $object['name'] ?? '');
        $detail = $object;

        if (in_array($type, ['virtual_machine', 'host'], true) && $externalId !== '') {
            try {
                $detail = array_replace($object, $client->detail($this->resourceFor($type), $externalId));
            } catch (VCenterApiException) {
                // List data remains useful when detail permission is unavailable.
            }
        }

        $stableId = $this->stableId($type, $detail, $externalId);
        $name = trim((string) ($detail['name'] ?? $externalId));
        $hostname = $type === 'virtual_machine'
            ? trim((string) ($detail['guest_hostname'] ?? $detail['name'] ?? $name))
            : ($type === 'host' ? trim((string) ($detail['name'] ?? $name)) : $name);
        $ips = $this->arrayValue($detail, ['ip_addresses', 'management_ip']);
        $macs = $this->arrayValue($detail, ['mac_addresses']);

        /*
         | O endpoint de detalhe da VM não devolve 'mac_addresses' nem
         | 'bios_uuid' no topo — os dados vêm aninhados em nics[] e identity.
         | Sem esta extração, MAC e serial ficavam nulos em todas as VMs,
         | ainda que presentes no raw_data.
         */
        $extraido = $type === 'virtual_machine'
            ? VCenterPayloadExtractor::fromVirtualMachine($detail)
            : [];

        if ($extraido !== []) {
            $macs = $macs ?: $extraido['macs'];
        }

        /*
         | IP só existe dentro do guest e exige VMware Tools — o detalhe da VM
         | jamais o devolve. Uma chamada extra por VM LIGADA resolve; máquina
         | desligada é pulada, porque Tools não responde nela.
         */
        $rede = [];

        if ($type === 'virtual_machine'
            && $this->coletaRedeHabilitada($connector)
            && ($detail['power_state'] ?? null) === 'POWERED_ON'
            && $externalId !== '') {
            $rede = $this->guestNetwork->coletar($client, $externalId);

            if ($rede['ips'] !== []) {
                $ips = $rede['ips'];
            }

            // O MAC do guest confirma o do hardware e cobre placas adicionais.
            if ($rede['macs'] !== []) {
                $macs = array_values(array_unique(array_merge($macs, $rede['macs'])));
            }
        }

        return [
            'external_id' => $stableId,
            'object_id' => $externalId,
            'hostname' => $hostname ?: null,
            'ip' => $ips[0] ?? null,
            'mac_address' => $macs[0] ?? null,
            'manufacturer' => $type === 'host' ? ($detail['vendor'] ?? null) : ($type === 'virtual_machine' ? 'VMware' : null),
            'model' => $type === 'virtual_machine' ? 'Virtual Machine' : ($detail['model'] ?? null),
            'serial_number' => $detail['serial_number']
                ?? ($extraido['bios_uuid'] ?? null)
                ?? ($detail['bios_uuid'] ?? ($detail['instance_uuid'] ?? null)),
            'description' => $detail['annotation'] ?? ($extraido['anotacao'] ?? null),
            'location' => $detail['datacenter'] ?? null,
            'source_type' => 'vcenter_' . $type,
            'source_hash' => hash('sha256', implode('|', [$connector->id, $type, $stableId])),
            'payload' => [
                'object_type' => $type,
                'stable_id' => $stableId,
                'source' => $detail,
                'ips' => $ips,
                'macs' => $macs,
                // Campos já normalizados, para quem consome não precisar
                // reinterpretar a estrutura crua do vSphere.
                'extraido' => $extraido ?: null,
                'rede_guest' => $rede ?: null,
            ],
        ];
    }

    private function objectTypes(CmdbConnector $connector): array
    {
        $configured = Arr::get($connector->settings ?? [], 'object_types', self::DEFAULT_OBJECT_TYPES);
        $configured = is_array($configured) ? $configured : self::DEFAULT_OBJECT_TYPES;

        return array_values(array_intersect(self::DEFAULT_OBJECT_TYPES, $configured)) ?: self::DEFAULT_OBJECT_TYPES;
    }

    private function resourceFor(string $type): string
    {
        return match ($type) {
            'virtual_machine' => 'vm',
            'datacenter' => 'datacenter',
            'cluster' => 'cluster',
            'host' => 'host',
            'datastore' => 'datastore',
            'network' => 'network',
            default => $type,
        };
    }

    private function passesFilter(CmdbConnector $connector, string $type, array $object): bool
    {
        $settings = $connector->settings ?? [];
        $filter = match ($type) {
            'datacenter' => Arr::get($settings, 'datacenter_filter', []),
            'cluster' => Arr::get($settings, 'cluster_filter', []),
            default => [],
        };

        if (! is_array($filter) || $filter === []) {
            return true;
        }

        $name = Str::lower(trim((string) ($object['name'] ?? '')));

        return in_array($name, array_map(static fn ($value): string => Str::lower(trim((string) $value)), $filter), true);
    }

    private function isCompleteRun(CmdbConnector $connector, array $types): bool
    {
        if ((int) Arr::get($connector->settings ?? [], 'runtime_limit', 0) > 0) return false;
        foreach (['datacenter_filter', 'cluster_filter'] as $key) {
            if (Arr::get($connector->settings ?? [], $key, []) !== []) return false;
        }
        return $types === self::DEFAULT_OBJECT_TYPES;
    }

    private function idKeyFor(string $type): string
    {
        return match ($type) {
            'datacenter' => 'datacenter',
            'cluster' => 'cluster',
            'host' => 'host',
            'virtual_machine' => 'vm',
            'datastore' => 'datastore',
            'network' => 'network',
            default => 'id',
        };
    }

    private function stableId(string $type, array $data, string $externalId): string
    {
        if ($type === 'virtual_machine') {
            return trim((string) ($data['instance_uuid'] ?? $data['bios_uuid'] ?? $externalId));
        }

        if ($type === 'host') {
            return trim((string) ($data['serial_number'] ?? $data['bios_uuid'] ?? $externalId));
        }

        return trim($externalId);
    }

    private function arrayValue(array $data, array $keys): array
    {
        foreach ($keys as $key) {
            $value = $data[$key] ?? null;
            if (is_array($value)) {
                return array_values(array_filter(array_map(static fn ($item): string => trim((string) (is_array($item) ? ($item['ip_address'] ?? $item['mac_address'] ?? '') : $item)), $value)));
            }
            if (is_string($value) && trim($value) !== '') {
                return [trim($value)];
            }
        }

        return [];
    }

    private function failure(VCenterApiException $exception, float $startedAt): array
    {
        return [
            'success' => false,
            'status' => 'failed',
            'source' => 'vcenter',
            'message' => match ($exception->kind) {
                'authentication' => 'Credenciais do vCenter inválidas.',
                'authorization' => 'Usuário sem permissão de leitura no vCenter.',
                'configuration' => $exception->getMessage(),
                'connection' => 'Não foi possível conectar ao vCenter.',
                default => 'Falha ao consultar o inventário do vCenter.',
            },
            'data' => [
                'error_kind' => $exception->kind,
                'response_time_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ],
            'items' => [],
            'warnings' => [],
            'meta' => ['connector_type' => 'vcenter'],
        ];
    }
}
