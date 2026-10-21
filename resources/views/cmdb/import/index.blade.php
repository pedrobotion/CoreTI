<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @include('cmdb.partials._nav', ['title' => 'Importação', 'description' => 'Validação e carga em massa de ativos por CSV/XLSX.'])

        @if(session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('cmdb.import.template', ['format' => 'xlsx']) }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Baixar template XLSX</a>
                <a href="{{ route('cmdb.import.template', ['format' => 'csv']) }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Baixar template CSV</a>
                <a href="{{ route('cmdb.import.batches.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Lotes de importação</a>
            </div>

            <form method="POST" action="{{ route('cmdb.import.preview') }}" enctype="multipart/form-data" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @csrf
                <div class="md:col-span-2 xl:col-span-3">
                    <label class="text-sm font-medium text-slate-700">Arquivo CSV/XLSX</label>
                    <input type="file" name="file" accept=".csv,.txt,.xls,.xlsx" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>

                <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="create_manufacturers" value="1"> Criar fabricantes inexistentes</label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="create_models" value="1"> Criar modelos inexistentes</label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="create_racks" value="1"> Criar racks inexistentes</label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="ignore_errors" value="1"> Ignorar linhas com erro</label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="update_existing" value="1"> Atualizar ativos existentes</label>

                <div class="md:col-span-2 xl:col-span-3 flex justify-end">
                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Gerar prévia</button>
                </div>
            </form>
        </section>

        @if($batch)
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Prévia da importação</h2>
                        <p class="text-sm text-slate-500">{{ $batch->original_name }} | {{ $batch->created_at?->format('d/m/Y H:i') }}</p>
                    </div>
                    <form method="POST" action="{{ route('cmdb.import.confirm', $batch) }}">
                        @csrf
                        <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Confirmar importação</button>
                    </form>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-4 xl:grid-cols-6">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase text-slate-500">Total</p>
                        <p class="mt-1 text-2xl font-bold text-slate-900">{{ $batch->total_rows }}</p>
                    </div>
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                        <p class="text-xs font-semibold uppercase text-emerald-700">Válidas</p>
                        <p class="mt-1 text-2xl font-bold text-emerald-900">{{ $batch->valid_rows }}</p>
                    </div>
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                        <p class="text-xs font-semibold uppercase text-amber-700">Alertas</p>
                        <p class="mt-1 text-2xl font-bold text-amber-900">{{ $batch->warning_rows }}</p>
                    </div>
                    <div class="rounded-lg border border-rose-200 bg-rose-50 p-4">
                        <p class="text-xs font-semibold uppercase text-rose-700">Erros</p>
                        <p class="mt-1 text-2xl font-bold text-rose-900">{{ $batch->error_rows }}</p>
                    </div>
                </div>

                <div class="mt-5 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            <tr>
                                <th class="px-4 py-3">Linha</th>
                                <th class="px-4 py-3">Tipo</th>
                                <th class="px-4 py-3">Nome</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Ação</th>
                                <th class="px-4 py-3">Mensagens</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach($rows as $row)
                                <tr>
                                    <td class="px-4 py-3">{{ $row->row_number }}</td>
                                    <td class="px-4 py-3">{{ data_get($row->data, 'normalized.asset_type', '-') }}</td>
                                    <td class="px-4 py-3">{{ data_get($row->data, 'normalized.name', '-') }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $row->status === 'valid' ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200' : ($row->status === 'warning' ? 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200' : 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200') }}">
                                            {{ ucfirst($row->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">{{ $row->action ?: '-' }}</td>
                                    <td class="px-4 py-3">
                                        @if(!empty($row->messages))
                                            <ul class="list-disc space-y-1 pl-5 text-slate-600">
                                                @foreach($row->messages as $message)
                                                    <li>{{ $message }}</li>
                                                @endforeach
                                            </ul>
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if($recentBatches->isNotEmpty())
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">Últimos lotes</h2>
                <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    @foreach($recentBatches as $item)
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <p class="text-sm font-semibold text-slate-900">{{ $item->original_name }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $item->status }} · {{ $item->started_at?->format('d/m/Y H:i') }}</p>
                            <p class="mt-2 text-sm text-slate-600">Importados: {{ $item->imported_rows }} | Atualizados: {{ $item->updated_rows }} | Falhas: {{ $item->failed_rows }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
