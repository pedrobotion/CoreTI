<?php

namespace App\Services\Cmdb\Discovery;

use App\Services\Cmdb\Discovery\Connectors\VCenterApiClient;
use Illuminate\Support\Arr;

/**
 * Coleta a rede vista de DENTRO do guest.
 *
 * Motivo de existir: /api/vcenter/vm/{id} devolve o hardware (portgroup, MAC),
 * mas nunca o endereço IP. O IP só existe do lado do sistema operacional e é
 * exposto em /guest/networking/interfaces, que depende do VMware Tools.
 *
 * Era essa a causa de nenhuma das 290 VMs ter IP no CMDB: a rota nunca era
 * chamada. Medido no ambiente: 29 de 30 VMs ligadas respondem, ~100ms cada.
 *
 * Só faz sentido para VMs ligadas — Tools não roda em máquina desligada.
 */
class VCenterGuestNetworkCollector
{
    /**
     * @return array{
     *     disponivel: bool,
     *     ips: array<int, string>,
     *     macs: array<int, string>,
     *     interfaces: array<int, array<string, mixed>>,
     *     gateways: array<int, string>,
     *     erro: ?string
     * }
     */
    public function coletar(VCenterApiClient $client, string $vmId): array
    {
        $vazio = [
            'disponivel' => false,
            'ips' => [],
            'macs' => [],
            'interfaces' => [],
            'gateways' => [],
            'erro' => null,
        ];

        try {
            $interfaces = $client->detail("vm/{$vmId}/guest/networking", 'interfaces');
        } catch (\Throwable $e) {
            // Tools ausente ou parado devolve erro; não é falha da auditoria.
            return $vazio + ['erro' => mb_substr($e->getMessage(), 0, 200)];
        }

        if (! is_array($interfaces) || $interfaces === []) {
            return $vazio;
        }

        $normalizadas = [];
        $ips = [];
        $macs = [];

        foreach ($interfaces as $iface) {
            if (! is_array($iface)) {
                continue;
            }

            $mac = $iface['mac_address'] ?? null;
            $enderecos = Arr::get($iface, 'ip.ip_addresses', []);
            $dns = Arr::get($iface, 'dns.ip_addresses', []);

            foreach ($enderecos as $endereco) {
                $ip = $endereco['ip_address'] ?? null;

                if (! $ip || $this->ehDescartavel($ip)) {
                    continue;
                }

                $ips[] = $ip;

                $normalizadas[] = [
                    'ip' => $ip,
                    'prefixo' => $endereco['prefix_length'] ?? null,
                    'mascara' => $this->prefixoParaMascara($endereco['prefix_length'] ?? null),
                    'origem' => $endereco['origin'] ?? null,
                    'estado' => $endereco['state'] ?? null,
                    'mac' => $mac,
                    'dns' => array_values(array_filter($dns, fn ($d) => ! $this->ehDescartavel((string) $d))),
                    'nic' => $iface['nic'] ?? null,
                ];
            }

            if ($mac) {
                $macs[] = $mac;
            }
        }

        return [
            'disponivel' => true,
            'ips' => array_values(array_unique($ips)),
            'macs' => array_values(array_unique($macs)),
            'interfaces' => $normalizadas,
            'gateways' => $this->coletarGateways($client, $vmId),
            'erro' => null,
        ];
    }

    /**
     * Gateway vem da tabela de rotas do guest, em chamada separada.
     *
     * @return array<int, string>
     */
    private function coletarGateways(VCenterApiClient $client, string $vmId): array
    {
        try {
            $rotas = $client->detail("vm/{$vmId}/guest/networking", 'routes');
        } catch (\Throwable) {
            return [];
        }

        if (! is_array($rotas)) {
            return [];
        }

        $gateways = [];

        foreach ($rotas as $rota) {
            $gateway = is_array($rota) ? ($rota['gateway_address'] ?? null) : null;

            if ($gateway && ! $this->ehDescartavel((string) $gateway)) {
                $gateways[] = $gateway;
            }
        }

        return array_values(array_unique($gateways));
    }

    /**
     * Loopback e link-local poluem o inventário sem identificar a máquina.
     */
    private function ehDescartavel(string $ip): bool
    {
        return $ip === ''
            || str_starts_with($ip, '127.')
            || str_starts_with($ip, '169.254.')
            || $ip === '::1'
            || str_starts_with(mb_strtolower($ip), 'fe80:');
    }

    private function prefixoParaMascara(mixed $prefixo): ?string
    {
        $prefixo = (int) $prefixo;

        // IPv6 não tem máscara decimal; devolvemos apenas o prefixo nesses casos.
        if ($prefixo < 1 || $prefixo > 32) {
            return null;
        }

        return long2ip(-1 << (32 - $prefixo));
    }
}
