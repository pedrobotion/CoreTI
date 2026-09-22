<?php

namespace App\Services\Cmdb\Discovery;

use Illuminate\Support\Arr;

/**
 * Extrai campos úteis do retorno de /api/vcenter/vm/{id}.
 *
 * Existe porque a extração anterior procurava chaves de topo que o vSphere
 * não devolve nesse endpoint — 'mac_addresses', 'bios_uuid', 'guest_hostname'.
 * O dado sempre chegou; estava aninhado. Resultado: MAC, serial e SO ficaram
 * nulos em todas as VMs, apesar de presentes no raw_data.
 *
 * Usado tanto pelo conector (sincronização nova) quanto pelo comando de
 * reprocessamento (raw_data já gravado), para que os dois não divirjam.
 *
 * NÃO cobre IP de guest: esse dado não vem neste endpoint, e sim em
 * /api/vcenter/vm/{id}/guest/identity, que exige VMware Tools.
 */
class VCenterPayloadExtractor
{
    /**
     * @param  array<string, mixed>  $source  bloco 'source' do payload da VM
     * @return array<string, mixed>
     */
    public static function fromVirtualMachine(array $source): array
    {
        $nics = self::asList(Arr::get($source, 'nics'));
        $discos = self::asList(Arr::get($source, 'disks'));

        $capacidadeBytes = 0;
        $datastores = [];

        foreach ($discos as $disco) {
            $capacidadeBytes += (int) ($disco['capacity'] ?? 0);

            // O caminho vem como "[NOME_DO_DATASTORE] pasta/arquivo.vmdk".
            if (preg_match('/^\[([^\]]+)\]/', (string) Arr::get($disco, 'backing.vmdk_file'), $m)) {
                $datastores[] = $m[1];
            }
        }

        $memoriaMib = (int) (Arr::get($source, 'memory.size_MiB') ?? Arr::get($source, 'memory_size_MiB') ?? 0);

        return [
            'macs' => array_values(array_filter(array_map(
                fn (array $nic) => $nic['mac_address'] ?? null,
                $nics
            ))),
            'redes' => array_values(array_unique(array_filter(array_map(
                fn (array $nic) => Arr::get($nic, 'backing.network_name'),
                $nics
            )))),
            'bios_uuid' => Arr::get($source, 'identity.bios_uuid'),
            'instance_uuid' => Arr::get($source, 'identity.instance_uuid'),
            'guest_os' => Arr::get($source, 'guest_OS'),
            'power_state' => Arr::get($source, 'power_state'),
            'cpu_count' => (int) (Arr::get($source, 'cpu.count') ?? Arr::get($source, 'cpu_count') ?? 0) ?: null,
            'cores_por_socket' => Arr::get($source, 'cpu.cores_per_socket'),
            'memoria_mib' => $memoriaMib ?: null,
            'memoria_gb' => $memoriaMib ? round($memoriaMib / 1024, 2) : null,
            'disco_bytes' => $capacidadeBytes ?: null,
            'disco_gb' => $capacidadeBytes ? round($capacidadeBytes / (1024 ** 3), 2) : null,
            'datastores' => array_values(array_unique($datastores)),
            'versao_hardware' => Arr::get($source, 'hardware.version'),
            'firmware' => Arr::get($source, 'boot.type'),
            'anotacao' => Arr::get($source, 'annotation') ?: null,
        ];
    }

    /**
     * O vSphere devolve nics e disks como MAPA (chave = id do dispositivo),
     * não como lista. Normaliza para iteração.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function asList(mixed $valor): array
    {
        if (! is_array($valor)) {
            return [];
        }

        return array_values(array_filter($valor, 'is_array'));
    }
}
