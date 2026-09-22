<?php

namespace App\Services\Cmdb\Veeam;

use App\Models\CmdbConnector;

/**
 * Boundary for the official Veeam API.
 *
 * Veeam REST resources differ between VBR and Enterprise Manager versions.
 * The client therefore refuses to guess an API family or endpoint until the
 * connector explicitly identifies the product and version.
 */
class VeeamApiClient
{
    public function configuration(CmdbConnector $connector): array
    {
        $settings = is_array($connector->settings) ? $connector->settings : [];

        return [
            'product' => trim((string) ($settings['api_product'] ?? '')),
            'version' => trim((string) ($settings['api_version'] ?? '')),
            'base_path' => trim((string) ($settings['api_base_path'] ?? '')),
            'configured' => filled($connector->host)
                && filled($connector->username)
                && filled($connector->password_encrypted)
                && filled($settings['api_product'] ?? null)
                && filled($settings['api_version'] ?? null),
        ];
    }

    public function assertOfficialApiConfigured(CmdbConnector $connector): array
    {
        $configuration = $this->configuration($connector);

        if (! $configuration['configured']) {
            throw new VeeamApiException(
                'O conector Veeam precisa informar produto, versão da API oficial, URL base, usuário e senha antes do teste ou da sincronização.'
            );
        }

        if (! in_array($configuration['product'], ['vbr', 'enterprise_manager'], true)) {
            throw new VeeamApiException('Produto Veeam não suportado/configurado. Use vbr ou enterprise_manager após confirmar a versão instalada.');
        }

        return $configuration;
    }

    public function testConnection(CmdbConnector $connector): array
    {
        $configuration = $this->assertOfficialApiConfigured($connector);

        return [
            'ok' => false,
            'message' => sprintf(
                'API Veeam configurada como %s %s, mas o adaptador oficial ainda precisa ser habilitado para essa versão; nenhum endpoint foi presumido.',
                $configuration['product'],
                $configuration['version']
            ),
            'configuration' => $configuration,
        ];
    }

    public function fetchProtectionData(CmdbConnector $connector, array $options = []): array
    {
        $this->assertOfficialApiConfigured($connector);

        throw new VeeamApiException(
            'A sincronização Veeam está bloqueada até a versão/API oficial do ambiente ser confirmada e o adaptador correspondente ser configurado.'
        );
    }
}
