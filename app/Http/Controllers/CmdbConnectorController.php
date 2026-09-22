<?php

namespace App\Http\Controllers;

use App\Models\CmdbConnector;
use App\Services\CmdbSyncService;
use App\Services\Cmdb\Veeam\VeeamApiException;
use App\Services\Cmdb\Veeam\VeeamBackupSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CmdbConnectorController extends Controller
{
    public function index(Request $request): View
    {
        if (! Schema::hasTable('cmdb_connectors')) {
            return view('cmdb.connectors.index', [
                'items' => collect(),
                'filters' => [],
                'setupPending' => true,
                'types' => CmdbConnector::TYPES,
                'statuses' => CmdbConnector::STATUSES,
            ]);
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'connector_type' => ['nullable', Rule::in(array_keys(CmdbConnector::TYPES))],
            'status' => ['nullable', Rule::in(array_keys(CmdbConnector::STATUSES))],
            'is_active' => ['nullable', Rule::in(['0', '1'])],
        ]);

        $query = CmdbConnector::query()->withCount(['syncJobs', 'syncLogs']);

        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('host', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['connector_type'])) {
            $query->where('connector_type', $filters['connector_type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (array_key_exists('is_active', $filters)) {
            $query->where('is_active', (bool) ((int) $filters['is_active']));
        }

        return view('cmdb.connectors.index', [
            'items' => $query->orderBy('name')->paginate(15)->withQueryString(),
            'filters' => $filters,
            'setupPending' => false,
            'types' => CmdbConnector::TYPES,
            'statuses' => CmdbConnector::STATUSES,
        ]);
    }

    public function create(): View
    {
        return view('cmdb.connectors.create', [
            'connector' => new CmdbConnector(),
            'types' => CmdbConnector::TYPES,
            'statuses' => CmdbConnector::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $connector = CmdbConnector::create($this->validated($request));

        return redirect()->route('cmdb.connectors.show', $connector)->with('success', 'Conector cadastrado com sucesso.');
    }

    public function show(CmdbConnector $connector): View
    {
        $connector->load([
            'syncJobs' => fn ($query) => $query->orderByDesc('created_at')->limit(10),
            'syncLogs' => fn ($query) => $query->orderByDesc('started_at')->limit(12),
            'assets' => fn ($query) => $query->orderByDesc('updated_at')->limit(10),
        ]);

        return view('cmdb.connectors.show', [
            'connector' => $connector,
            'types' => CmdbConnector::TYPES,
            'statuses' => CmdbConnector::STATUSES,
        ]);
    }

    public function edit(CmdbConnector $connector): View
    {
        return view('cmdb.connectors.edit', [
            'connector' => $connector,
            'types' => CmdbConnector::TYPES,
            'statuses' => CmdbConnector::STATUSES,
        ]);
    }

    public function update(Request $request, CmdbConnector $connector): RedirectResponse
    {
        $connector->update($this->validated($request, $connector));

        return redirect()->route('cmdb.connectors.show', $connector)->with('success', 'Conector atualizado com sucesso.');
    }

    public function destroy(CmdbConnector $connector): RedirectResponse
    {
        $connector->delete();

        return redirect()->route('cmdb.connectors.index')->with('success', 'Conector removido com sucesso.');
    }

    public function test(CmdbConnector $connector, CmdbSyncService $syncService, VeeamBackupSyncService $veeamSyncService): RedirectResponse
    {
        try {
            $result = $connector->connector_type === 'veeam'
                ? $veeamSyncService->testConnection($connector)
                : $syncService->testConnection($connector);
        } catch (VeeamApiException $exception) {
            $result = ['ok' => false, 'message' => $exception->getMessage()];
        }

        $message = $result['message'] ?? 'Teste executado com sucesso.';

        return redirect()
            ->route('cmdb.connectors.show', $connector)
            ->with((($result['ok'] ?? true) ? 'success' : 'error'), $message)
            ->with('connectorTestResult', $result);
    }

    public function syncNow(Request $request, CmdbConnector $connector, CmdbSyncService $syncService, VeeamBackupSyncService $veeamSyncService): RedirectResponse
    {
        if ($connector->connector_type === 'veeam') {
            try {
                $summary = $veeamSyncService->sync($connector);
                return back()->with('success', sprintf('Sincronização Veeam concluída. Encontrados: %d | Vinculados: %d | Atualizados: %d', $summary['found'], $summary['matched'], $summary['updated']));
            } catch (VeeamApiException $exception) {
                return back()->with('error', 'Sincronização Veeam não executada: '.$exception->getMessage());
            }
        }

        $result = $syncService->syncConnector($connector, $request->user());
        $summary = $result['summary'] ?? [];
        $message = sprintf(
            '%s | Encontrados: %d | Atualizados: %d | Sem alteração: %d',
            $result['message'] ?? 'Sincronização concluída.',
            (int) ($summary['assets_found'] ?? 0),
            (int) ($summary['assets_updated'] ?? 0),
            (int) ($summary['assets_unchanged'] ?? 0)
        );

        return $result['status'] === 'failed'
            ? back()->with('error', $message)
            : back()->with('success', $message);
    }

    private function validated(Request $request, ?CmdbConnector $connector = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'connector_type' => ['required', Rule::in(array_keys(CmdbConnector::TYPES))],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'token' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(array_keys(CmdbConnector::STATUSES))],
            'is_active' => ['nullable', 'boolean'],
            'verify_ssl' => ['nullable', 'boolean'],
            'timeout_seconds' => ['nullable', 'integer', 'min:1', 'max:300'],
            'sync_interval_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'import_powered_off' => ['nullable', 'boolean'],
            'settings' => ['nullable', 'string'],
            'snmp_version' => ['nullable', Rule::in(['2c', '3'])],
            'snmp_community' => ['nullable', 'string', 'max:255'],
            'snmp_timeout' => ['nullable', 'integer', 'min:1', 'max:60'],
            'snmp_retries' => ['nullable', 'integer', 'min:0', 'max:10'],
            'snmp_security_name' => ['nullable', 'string', 'max:255'],
            'snmp_auth_protocol' => ['nullable', 'string', 'max:255'],
            'snmp_auth_password' => ['nullable', 'string', 'max:255'],
            'snmp_priv_protocol' => ['nullable', 'string', 'max:255'],
            'snmp_priv_password' => ['nullable', 'string', 'max:255'],
            'veeam_api_product' => ['nullable', Rule::in(['vbr', 'enterprise_manager'])],
            'veeam_api_version' => ['nullable', 'string', 'max:40'],
            'veeam_api_base_path' => ['nullable', 'string', 'max:255'],
        ]);

        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        // Checkboxes desmarcados não são enviados pelo navegador.
        $data['verify_ssl'] = $request->boolean('verify_ssl');
        $data['timeout_seconds'] = (int) ($data['timeout_seconds'] ?? 30);
        $data['import_powered_off'] = (bool) ($data['import_powered_off'] ?? true);
        $settings = $this->decodeSettings($data['settings'] ?? null) ?? [];

        if (in_array(($data['connector_type'] ?? null), ['vmware', 'vcenter'], true)) {
            $settings['object_types'] = array_values(array_intersect(
                ['datacenter', 'cluster', 'host', 'virtual_machine', 'datastore', 'network'],
                (array) $request->input('vcenter_object_types', [])
            ));
            $settings['datacenter_filter'] = array_values(array_filter(array_map('trim', preg_split('/[,\n]+/', (string) $request->input('vcenter_datacenter_filter', '')) ?: [])));
            $settings['cluster_filter'] = array_values(array_filter(array_map('trim', preg_split('/[,\n]+/', (string) $request->input('vcenter_cluster_filter', '')) ?: [])));
        }

        unset($data['vcenter_object_types'], $data['vcenter_datacenter_filter'], $data['vcenter_cluster_filter']);

        if (($data['connector_type'] ?? null) === 'veeam') {
            $settings['api_product'] = $data['veeam_api_product'] ?? ($settings['api_product'] ?? null);
            $settings['api_version'] = trim((string) ($data['veeam_api_version'] ?? ($settings['api_version'] ?? '')));
            $settings['api_base_path'] = trim((string) ($data['veeam_api_base_path'] ?? ($settings['api_base_path'] ?? '')));
        }

        unset($data['veeam_api_product'], $data['veeam_api_version'], $data['veeam_api_base_path']);

        if (($data['connector_type'] ?? null) === 'snmp') {
            $settings['version'] = $data['snmp_version'] ?? '2c';
            $settings['timeout'] = (int) ($data['snmp_timeout'] ?? 2);
            $settings['retries'] = (int) ($data['snmp_retries'] ?? 1);
            $settings['security_name'] = $data['snmp_security_name'] ?? ($settings['security_name'] ?? null);
            $settings['auth_protocol'] = $data['snmp_auth_protocol'] ?? ($settings['auth_protocol'] ?? null);
            $settings['priv_protocol'] = $data['snmp_priv_protocol'] ?? ($settings['priv_protocol'] ?? null);

            if (filled($data['snmp_community'] ?? null)) {
                $settings['community_encrypted'] = Crypt::encryptString((string) $data['snmp_community']);
            } elseif ($connector) {
                $settings['community_encrypted'] = $connector->settings['community_encrypted'] ?? null;
            }

            if (filled($data['snmp_auth_password'] ?? null)) {
                $settings['auth_password_encrypted'] = Crypt::encryptString((string) $data['snmp_auth_password']);
            } elseif ($connector) {
                $settings['auth_password_encrypted'] = $connector->settings['auth_password_encrypted'] ?? null;
            }

            if (filled($data['snmp_priv_password'] ?? null)) {
                $settings['priv_password_encrypted'] = Crypt::encryptString((string) $data['snmp_priv_password']);
            } elseif ($connector) {
                $settings['priv_password_encrypted'] = $connector->settings['priv_password_encrypted'] ?? null;
            }
        }

        unset(
            $data['snmp_version'],
            $data['snmp_community'],
            $data['snmp_timeout'],
            $data['snmp_retries'],
            $data['snmp_security_name'],
            $data['snmp_auth_protocol'],
            $data['snmp_auth_password'],
            $data['snmp_priv_protocol'],
            $data['snmp_priv_password']
        );

        $data['settings'] = $settings;

        if (filled($data['password'] ?? null)) {
            $data['password_encrypted'] = Crypt::encryptString((string) $data['password']);
        } elseif ($connector) {
            $data['password_encrypted'] = $connector->password_encrypted;
        }

        if (filled($data['token'] ?? null)) {
            $data['token_encrypted'] = Crypt::encryptString((string) $data['token']);
        } elseif ($connector) {
            $data['token_encrypted'] = $connector->token_encrypted;
        }

        unset($data['password'], $data['token']);

        return $data;
    }

    private function decodeSettings(?string $settings): ?array
    {
        $settings = trim((string) $settings);

        if ($settings === '') {
            return null;
        }

        try {
            return json_decode($settings, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return ['raw' => $settings];
        }
    }
}
