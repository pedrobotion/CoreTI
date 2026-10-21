<x-app-layout>
    @php
        $typeLabels = [
            'warranty' => 'Garantia',
            'license' => 'Licença',
            'ssl_certificate' => 'Certificado SSL',
            'contract' => 'Contrato',
            'ups_battery' => 'Bateria de UPS',
            'manufacturer_support' => 'Suporte do fabricante',
            'domain' => 'Domínio',
            'backup_policy' => 'Política de backup',
            'other' => 'Outro',
        ];
        $statusLabels = [
            'pending' => 'Pendente',
            'notified' => 'Notificado',
            'renewed' => 'Renovado',
            'expired' => 'Vencido',
            'cancelled' => 'Cancelado',
        ];
    @endphp
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Vencimentos / Renovações', 'description' => 'Controle de alertas e renovações do CMDB.'])

        @if(!empty($setupPending))
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Estrutura do CMDB ainda não foi criada no banco. Execute as migrations para liberar o módulo.
            </div>
        @endif

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" class="flex flex-wrap items-end gap-2">
                <div class="min-w-[240px] flex-1">
                    <label class="text-xs font-semibold uppercase text-slate-500">Busca</label>
                    <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Título ou descrição" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div class="min-w-[180px]">
                    <label class="text-xs font-semibold uppercase text-slate-500">Tipo</label>
                    <select name="type" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Todos</option>
                        @foreach(['warranty','license','ssl_certificate','contract','ups_battery','manufacturer_support','domain','backup_policy','other'] as $type)
                            <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-[180px]">
                    <label class="text-xs font-semibold uppercase text-slate-500">Status</label>
                    <select name="status" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Todos</option>
                        @foreach(['pending','notified','renewed','expired','cancelled'] as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Buscar</button>
                    <a href="{{ route('cmdb.expirations.index') }}" class="inline-flex h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Limpar</a>
                </div>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">Novo vencimento</h2>
            <form method="POST" action="{{ route('cmdb.expirations.store') }}" class="mt-4 grid gap-3 md:grid-cols-2">
                @csrf
                <div>
                    <label class="text-sm font-medium text-slate-700">Ativo</label>
                    <select name="asset_id" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        @foreach($assets as $asset)
                            <option value="{{ $asset->id }}">{{ $asset->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Tipo</label>
                    <select name="expiration_type" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        @foreach(['warranty','license','ssl_certificate','contract','ups_battery','manufacturer_support','domain','backup_policy','other'] as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Título</label>
                    <input name="title" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Data de vencimento</label>
                    <input type="date" name="expiration_date" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Alertar com antecedência (dias)</label>
                    <input type="number" name="alert_days_before" value="30" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Status</label>
                    <select name="status" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        @foreach(['pending','notified','renewed','expired','cancelled'] as $status)
                            <option value="{{ $status }}">{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm font-medium text-slate-700">Descrição</label>
                    <textarea name="description" rows="3" class="mt-1 w-full rounded-md border-slate-300 text-sm"></textarea>
                </div>
                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Salvar</button>
                </div>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="px-5 py-3">Título</th>
                            <th class="px-5 py-3">Ativo</th>
                            <th class="px-5 py-3">Tipo</th>
                            <th class="px-5 py-3">Vencimento</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Jira</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($items as $item)
                            <tr>
                                <td class="px-5 py-3">{{ $item->title }}</td>
                                <td class="px-5 py-3">
                                    @if($item->asset)
                                        <a href="{{ route('cmdb.assets.show', $item->asset) }}" class="font-semibold text-[#033151] hover:underline">{{ $item->asset->name }}</a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-5 py-3">{{ $typeLabels[$item->expiration_type] ?? $item->expiration_type }}</td>
                                <td class="px-5 py-3">{{ optional($item->expiration_date)->format('d/m/Y') }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">{{ $statusLabels[$item->status] ?? $item->status }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        @if($item->jira_issue_key)
                                            <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">{{ $item->jira_issue_key }}</span>
                                            @if($item->jira_issue_url)
                                                <a href="{{ $item->jira_issue_url }}" target="_blank" class="inline-flex rounded-md border border-[#033151] bg-white px-3 py-1.5 text-xs font-semibold text-[#033151] hover:bg-slate-50">Abrir chamado Jira</a>
                                            @endif
                                        @else
                                            <form method="POST" action="{{ route('cmdb.expirations.jira', $item) }}">
                                                @csrf
                                                <button class="inline-flex rounded-md border border-[#033151] bg-white px-3 py-1.5 text-xs font-semibold text-[#033151] hover:bg-slate-50">Abrir chamado Jira</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-6 text-center text-slate-500">Nenhum vencimento cadastrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-5 py-4">
                {{ $items->links() }}
            </div>
        </section>
    </div>
</x-app-layout>
