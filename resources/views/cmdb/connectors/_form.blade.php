@php
    $connector = $connector ?? null;
    $isEdit = $isEdit ?? false;
    $currentType = old('connector_type', $connector->connector_type ?? 'manual');
    $settings = is_array($connector->settings ?? null) ? $connector->settings : [];
    $vcenterTypes = ['datacenter' => 'Datacenters', 'cluster' => 'Clusters', 'host' => 'Hosts ESXi', 'virtual_machine' => 'Máquinas virtuais', 'datastore' => 'Datastores', 'network' => 'Redes'];
    $selectedVcenterTypes = old('vcenter_object_types', $settings['object_types'] ?? array_keys($vcenterTypes));
    $veeamProduct = old('veeam_api_product', $settings['api_product'] ?? '');
@endphp

<form
    method="POST"
    action="{{ $isEdit ? route('cmdb.connectors.update', $connector) : route('cmdb.connectors.store') }}"
    class="space-y-6"
    x-data="{
        type: @js($currentType),
        syncSettings: {
            version: @js(old('snmp_version', $settings['version'] ?? '2c')),
            timeout: @js(old('snmp_timeout', $settings['timeout'] ?? 2)),
            retries: @js(old('snmp_retries', $settings['retries'] ?? 1)),
        }
    }"
    x-cloak
>
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <style>[x-cloak]{display:none !important;}</style>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div>
            <label class="text-sm font-medium text-slate-700">Nome</label>
            <input name="name" value="{{ old('name', $connector->name ?? '') }}" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">Tipo</label>
            <select name="connector_type" x-model="type" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                @foreach($types as $key => $label)
                    <option value="{{ $key }}" @selected(old('connector_type', $connector->connector_type ?? 'manual') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">Status</label>
            <select name="status" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                @foreach($statuses as $key => $label)
                    <option value="{{ $key }}" @selected(old('status', $connector->status ?? 'inactive') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">Host</label>
            <input name="host" value="{{ old('host', $connector->host ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">Porta</label>
            <input type="number" min="1" max="65535" name="port" value="{{ old('port', $connector->port ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">Usuário</label>
            <input name="username" value="{{ old('username', $connector->username ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">Senha</label>
            <input type="password" name="password" autocomplete="new-password" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="{{ $isEdit ? 'Deixe em branco para manter a atual' : 'Senha criptografada' }}">
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">Token</label>
            <input type="password" name="token" autocomplete="new-password" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="{{ $isEdit ? 'Deixe em branco para manter o atual' : 'Token criptografado' }}">
        </div>
        <div class="flex items-center gap-2 md:pt-8">
            <input id="is_active" type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-[#033151]" @checked(old('is_active', $connector->is_active ?? false))>
            <label for="is_active" class="text-sm font-medium text-slate-700">Ativo</label>
        </div>
        <div class="flex items-center gap-2 md:pt-8">
            <input id="verify_ssl" type="checkbox" name="verify_ssl" value="1" class="rounded border-slate-300 text-[#033151]" @checked(old('verify_ssl', $connector->verify_ssl ?? true))>
            <label for="verify_ssl" class="text-sm font-medium text-slate-700">Validar certificado TLS</label>
        </div>
        <div class="md:col-span-2 xl:col-span-3">
            <label class="text-sm font-medium text-slate-700">Configurações adicionais (JSON)</label>
            <textarea name="settings" rows="4" class="mt-1 w-full rounded-md border-slate-300 text-sm">{{ old('settings', isset($connector->settings) ? json_encode($connector->settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '') }}</textarea>
            <p class="mt-1 text-xs text-slate-500">Use este campo apenas para configurações não sensíveis. Community e senhas SNMP têm campos próprios.</p>
        </div>
    </div>

    <section x-show="type === 'vmware'" class="rounded-lg border border-blue-200 bg-blue-50 p-4 shadow-sm">
        <div>
            <h3 class="text-base font-semibold text-slate-900">Configuração VMware vCenter</h3>
            <p class="mt-1 text-sm text-slate-600">A descoberta usa somente consultas de inventário. Nenhuma VM ou configuração será alterada.</p>
        </div>
        <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div>
                <label class="text-sm font-medium text-slate-700">Timeout (segundos)</label>
                <input type="number" min="1" max="300" name="timeout_seconds" value="{{ old('timeout_seconds', $connector->timeout_seconds ?? 30) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Intervalo (minutos)</label>
                <input type="number" min="1" max="10080" name="sync_interval_minutes" value="{{ old('sync_interval_minutes', $connector->sync_interval_minutes ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
            <div class="flex items-center gap-2 md:pt-8">
                <input id="import_powered_off" type="checkbox" name="import_powered_off" value="1" class="rounded border-slate-300 text-[#033151]" @checked(old('import_powered_off', $connector->import_powered_off ?? true))>
                <label for="import_powered_off" class="text-sm font-medium text-slate-700">Importar VMs desligadas</label>
            </div>
            <div class="md:col-span-2 xl:col-span-3">
                <span class="text-sm font-medium text-slate-700">Tipos de objeto</span>
                <div class="mt-2 flex flex-wrap gap-3">
                    @foreach($vcenterTypes as $key => $label)
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="vcenter_object_types[]" value="{{ $key }}" class="rounded border-slate-300 text-[#033151]" @checked(in_array($key, (array) $selectedVcenterTypes, true))>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Filtro de datacenters</label>
                <textarea name="vcenter_datacenter_filter" rows="2" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="Um nome por linha, vazio = todos">{{ old('vcenter_datacenter_filter', implode("\n", (array) ($settings['datacenter_filter'] ?? []))) }}</textarea>
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Filtro de clusters</label>
                <textarea name="vcenter_cluster_filter" rows="2" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="Um nome por linha, vazio = todos">{{ old('vcenter_cluster_filter', implode("\n", (array) ($settings['cluster_filter'] ?? []))) }}</textarea>
            </div>
        </div>
        @if(old('verify_ssl', $connector->verify_ssl ?? true) === false)
            <p class="mt-3 rounded-md border border-amber-300 bg-amber-100 px-3 py-2 text-sm text-amber-900">A validação TLS está desativada. Use essa configuração apenas temporariamente.</p>
        @endif
    </section>

    <section x-show="type === 'snmp'" class="rounded-lg border border-slate-200 bg-slate-50 p-4 shadow-sm">
        <div class="flex items-center justify-between gap-2">
            <div>
                <h3 class="text-base font-semibold text-slate-900">Configuração SNMP</h3>
                <p class="text-sm text-slate-500">O conector SNMP v2c é suportado nesta etapa. SNMP v3 fica preparado para uso futuro.</p>
            </div>
            <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">SNMP</span>
        </div>

        <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div>
                <label class="text-sm font-medium text-slate-700">Versão SNMP</label>
                <select name="snmp_version" x-model="syncSettings.version" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    <option value="2c" @selected(old('snmp_version', $settings['version'] ?? '2c') === '2c')>2c</option>
                    <option value="3" @selected(old('snmp_version', $settings['version'] ?? '2c') === '3')>3</option>
                </select>
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Community</label>
                <input type="password" name="snmp_community" autocomplete="new-password" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="{{ $isEdit ? 'Deixe em branco para manter a atual' : 'Community SNMP' }}">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Timeout (s)</label>
                <input type="number" min="1" max="60" name="snmp_timeout" x-model="syncSettings.timeout" value="{{ old('snmp_timeout', $settings['timeout'] ?? 2) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Retries</label>
                <input type="number" min="0" max="10" name="snmp_retries" x-model="syncSettings.retries" value="{{ old('snmp_retries', $settings['retries'] ?? 1) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Security name</label>
                <input name="snmp_security_name" value="{{ old('snmp_security_name', $settings['security_name'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Auth protocol</label>
                <input name="snmp_auth_protocol" value="{{ old('snmp_auth_protocol', $settings['auth_protocol'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="MD5 / SHA">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Auth password</label>
                <input type="password" name="snmp_auth_password" autocomplete="new-password" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="{{ $isEdit ? 'Deixe em branco para manter a atual' : 'Senha SNMP v3' }}">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Priv protocol</label>
                <input name="snmp_priv_protocol" value="{{ old('snmp_priv_protocol', $settings['priv_protocol'] ?? '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="DES / AES">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Priv password</label>
                <input type="password" name="snmp_priv_password" autocomplete="new-password" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="{{ $isEdit ? 'Deixe em branco para manter a atual' : 'Senha SNMP v3' }}">
            </div>
        </div>
    </section>

    <section x-show="type === 'veeam'" class="rounded-lg border border-amber-200 bg-amber-50 p-4 shadow-sm">
        <h3 class="text-base font-semibold text-slate-900">Configuração Veeam</h3>
        <p class="mt-1 text-sm text-amber-900">Informe o produto e a versão exata da API oficial. O CoreTI não presume endpoints entre versões e não consulta o banco interno do Veeam.</p>
        <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div>
                <label class="text-sm font-medium text-slate-700">Produto/API oficial</label>
                <select name="veeam_api_product" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    <option value="">Selecione</option>
                    <option value="vbr" @selected($veeamProduct === 'vbr')>Veeam Backup & Replication</option>
                    <option value="enterprise_manager" @selected($veeamProduct === 'enterprise_manager')>Veeam Enterprise Manager</option>
                </select>
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Versão da API</label>
                <input name="veeam_api_version" value="{{ old('veeam_api_version', $settings['api_version'] ?? '') }}" placeholder="Ex.: v12.1" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Caminho base da API (opcional)</label>
                <input name="veeam_api_base_path" value="{{ old('veeam_api_base_path', $settings['api_base_path'] ?? '') }}" placeholder="Somente se informado pela documentação da versão" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Timeout (segundos)</label>
                <input type="number" min="1" max="300" name="timeout_seconds" value="{{ old('timeout_seconds', $connector->timeout_seconds ?? 30) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Intervalo (minutos)</label>
                <input type="number" min="1" max="10080" name="sync_interval_minutes" value="{{ old('sync_interval_minutes', $connector->sync_interval_minutes ?? 60) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
        </div>
    </section>

    <div class="flex justify-end gap-2">
        <a href="{{ route('cmdb.connectors.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Voltar</a>
        <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">{{ $isEdit ? 'Salvar alterações' : 'Salvar conector' }}</button>
    </div>
</form>
