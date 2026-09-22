<?php

namespace App\Services\Cmdb\Discovery\Connectors;

use App\Models\CmdbConnector;
use App\Services\Cmdb\Discovery\DiscoveredAssetData;
use Illuminate\Support\Str;

class SnmpConnector extends AbstractConnector
{
    private const OIDS = [
        'sysDescr' => '1.3.6.1.2.1.1.1.0',
        'sysObjectID' => '1.3.6.1.2.1.1.2.0',
        'sysUpTime' => '1.3.6.1.2.1.1.3.0',
        'sysContact' => '1.3.6.1.2.1.1.4.0',
        'sysName' => '1.3.6.1.2.1.1.5.0',
        'sysLocation' => '1.3.6.1.2.1.1.6.0',
    ];

    public function testConnection(CmdbConnector $connector): array
    {
        $startedAt = microtime(true);

        if (! function_exists('snmp2_get')) {
            return [
                'success' => false,
                'status' => 'failed',
                'source' => 'snmp',
                'message' => 'Extensão SNMP do PHP não está disponível neste servidor.',
                'data' => [],
                'items' => [],
                'warnings' => [],
                'meta' => ['connector_type' => $connector->connector_type],
            ];
        }

        $response = $this->snmpSnapshot($connector);
        $response['response_time_ms'] = (int) round((microtime(true) - $startedAt) * 1000);

        $success = ! empty($response['hostname']) || ! empty($response['description']);

        return [
            'success' => $success,
            'status' => $success ? 'success' : 'failed',
            'source' => 'snmp',
            'message' => $success
                ? 'Conexão SNMP testada com sucesso.'
                : 'Não foi possível obter resposta do dispositivo via SNMP.',
            'data' => $response,
            'items' => [],
            'warnings' => $success ? [] : ['Sem resposta útil via SNMP.'],
            'meta' => ['connector_type' => $connector->connector_type],
        ];
    }

    public function discover(CmdbConnector $connector): array
    {
        if (! function_exists('snmp2_get')) {
            return [
                'success' => false,
                'status' => 'failed',
                'source' => 'snmp',
                'message' => 'Extensão SNMP do PHP não está disponível neste servidor.',
                'data' => [],
                'items' => [],
                'warnings' => [],
                'meta' => ['connector_type' => $connector->connector_type],
            ];
        }

        $startedAt = microtime(true);
        $snapshot = $this->snmpSnapshot($connector);
        $snapshot['response_time_ms'] = (int) round((microtime(true) - $startedAt) * 1000);
        $snapshot['external_id'] = $snapshot['object_id'] ?? $snapshot['hostname'] ?? $connector->host;

        $discovered = DiscoveredAssetData::fromArray([
            'external_id' => $snapshot['external_id'],
            'hostname' => $snapshot['hostname'] ?: $connector->host,
            'description' => $snapshot['description'],
            'object_id' => $snapshot['object_id'],
            'uptime' => $snapshot['uptime'],
            'contact' => $snapshot['contact'],
            'location' => $snapshot['location'],
            'ip' => $connector->host,
            'response_time_ms' => $snapshot['response_time_ms'],
            'recommendation' => 'review_required',
        ]);

        return [
            'success' => true,
            'status' => 'warning',
            'source' => 'snmp',
            'message' => 'Descoberta SNMP executada. Revisão recomendada.',
            'data' => $snapshot,
            'items' => [$discovered->toArray()],
            'warnings' => [],
            'meta' => ['connector_type' => $connector->connector_type],
        ];
    }

    public function sync(CmdbConnector $connector): array
    {
        return $this->discover($connector);
    }

    private function snmpSnapshot(CmdbConnector $connector): array
    {
        if (blank($connector->host)) {
            return [
                'hostname' => null,
                'description' => null,
                'object_id' => null,
                'uptime' => null,
                'contact' => null,
                'location' => null,
                'ip' => null,
                'error' => 'Host SNMP não informado.',
            ];
        }

        $target = $this->target($connector);
        $community = $this->secretSetting($connector, 'community');
        $timeout = max(1, (int) $this->setting($connector, 'timeout', 2));
        $retries = max(0, (int) $this->setting($connector, 'retries', 1));

        if (! $community) {
            return [
                'hostname' => null,
                'description' => null,
                'object_id' => null,
                'uptime' => null,
                'contact' => null,
                'location' => null,
                'ip' => $connector->host,
                'error' => 'Community SNMP não configurada.',
            ];
        }

        $this->configureSnmp();

        $snapshot = [
            'hostname' => null,
            'description' => null,
            'object_id' => null,
            'uptime' => null,
            'contact' => null,
            'location' => null,
            'ip' => $connector->host,
            'error' => null,
        ];

        foreach (self::OIDS as $key => $oid) {
            $value = $this->safeSnmpGet($target, $community, $oid, $timeout, $retries);
            $snapshot[$key === 'sysName' ? 'hostname' : ($key === 'sysDescr' ? 'description' : ($key === 'sysObjectID' ? 'object_id' : ($key === 'sysUpTime' ? 'uptime' : ($key === 'sysContact' ? 'contact' : 'location'))))] = $value;
        }

        return $snapshot;
    }

    private function target(CmdbConnector $connector): string
    {
        $host = trim((string) $connector->host);
        $port = (int) ($connector->port ?: 161);

        return 'udp:' . $host . ':' . $port;
    }

    private function configureSnmp(): void
    {
        if (function_exists('snmp_set_quick_print')) {
            @snmp_set_quick_print(true);
        }

        if (function_exists('snmp_set_valueretrieval') && defined('SNMP_VALUE_PLAIN')) {
            @snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
        }
    }

    private function safeSnmpGet(string $target, string $community, string $oid, int $timeout, int $retries): ?string
    {
        $previous = set_error_handler(static fn () => true);

        try {
            $value = @snmp2_get($target, $community, $oid, $timeout * 1000000, $retries);
        } finally {
            if ($previous !== null) {
                set_error_handler($previous);
            } else {
                restore_error_handler();
            }
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return $this->normalizeValue($value);
    }

    private function normalizeValue(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/^[A-Z ]+:\\s*/', '', $value) ?? $value;
        $value = preg_replace('/^STRING:\\s*/i', '', $value) ?? $value;
        $value = preg_replace('/^Timeticks:\\s*/i', '', $value) ?? $value;
        $value = preg_replace('/^OID:\\s*/i', '', $value) ?? $value;
        $value = preg_replace('/^Hex-STRING:\\s*/i', '', $value) ?? $value;

        return trim($value, " \t\n\r\0\x0B\"");
    }
}
