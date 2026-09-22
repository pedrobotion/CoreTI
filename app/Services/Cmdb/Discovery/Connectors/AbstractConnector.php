<?php

namespace App\Services\Cmdb\Discovery\Connectors;

use App\Models\CmdbConnector;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;

abstract class AbstractConnector implements ConnectorInterface
{
    protected function settings(CmdbConnector $connector): array
    {
        return is_array($connector->settings) ? $connector->settings : [];
    }

    protected function setting(CmdbConnector $connector, string $key, mixed $default = null): mixed
    {
        $settings = $this->settings($connector);

        return Arr::get($settings, $key, $default);
    }

    protected function secretSetting(CmdbConnector $connector, string $key, ?string $default = null): ?string
    {
        $settings = $this->settings($connector);

        $encryptedKey = $key . '_encrypted';
        if (! empty($settings[$encryptedKey])) {
            try {
                return Crypt::decryptString((string) $settings[$encryptedKey]);
            } catch (\Throwable) {
                return $default;
            }
        }

        $value = Arr::get($settings, $key);
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return $default;
    }
}
