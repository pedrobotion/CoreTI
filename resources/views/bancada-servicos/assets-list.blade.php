<x-app-layout>
    @include('bancada-servicos.partials.label-printer')
    <div class="mx-auto max-w-7xl space-y-6" x-data="{ addModalOpen: {{ old('tipo_equipamento') || old('plaqueta') ? 'true' : 'false' }}, documentsModalOpen: false, documentsList: null, documentEquipmentTag: '', loadingDocuments: false, openDocuments: async function(url, plaqueta) { this.documentsList = null; this.documentEquipmentTag = plaqueta; this.loadingDocuments = true; try { const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' }); if (!res.ok) { const body = await res.text().catch(() => ''); console.error('Erro ao carregar documentos', { status: res.status, statusText: res.statusText, body }); throw new Error(`Erro HTTP ${res.status}`); } const data = await res.json(); this.documentsList = data.documents || []; this.documentsModalOpen = true; } catch (e) { console.error(e); alert('Erro ao carregar documentos do equipamento.'); } finally { this.loadingDocuments = false; } } }">
        <div class="flex items-end justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-400">Bancada de Serviços</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl dark:text-white">{{ $title }}</h1>
            </div>
            <div class="flex items-center gap-2">
                @if($isBackup)
                    <a href="{{ route('bancada-servicos.assets.backup.print-template') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700">
                        Imprimir etiqueta de Backup
                    </a>
                @endif
                <button type="button" @click="addModalOpen = true" style="background-color: #033151; border-color: #033151;" class="inline-flex min-h-10 items-center justify-center rounded-lg px-4 text-sm font-semibold text-white hover:opacity-90">Adicionar equipamento</button>
            </div>
        </div>
        @if(isset($statusCounts) && $statusCounts->isNotEmpty())
            @php
                $statusCardClasses = [
                    'Em bancada' => 'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-900/40 dark:bg-sky-500/10 dark:text-sky-200',
                    'Em manutenção' => 'border-indigo-200 bg-indigo-50 text-indigo-900 dark:border-indigo-900/40 dark:bg-indigo-500/10 dark:text-indigo-200',
                    'Aguardando peça' => 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/40 dark:bg-amber-500/10 dark:text-amber-200',
                    'Aguardando Entrada Fiscal' => 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/40 dark:bg-amber-500/10 dark:text-amber-200',
                    'Terceiros' => 'border-violet-200 bg-violet-50 text-violet-900 dark:border-violet-900/40 dark:bg-violet-500/10 dark:text-violet-200',
                    'Manutenção realizada' => 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-500/10 dark:text-emerald-200',
                    'Pronto para entrega' => 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-500/10 dark:text-emerald-200',
                    'Sem conserto' => 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-900/40 dark:bg-rose-500/10 dark:text-rose-200',
                    'Descarte' => 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-900/40 dark:bg-rose-500/10 dark:text-rose-200',
                    'Nota Fiscal Emitida' => 'border-teal-200 bg-teal-50 text-teal-900 dark:border-teal-900/40 dark:bg-teal-500/10 dark:text-teal-200',
                    'Entregue' => 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-500/10 dark:text-emerald-200',
                    'Backup' => 'border-slate-200 bg-slate-50 text-slate-800 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-200',
                ];
            @endphp
            <section class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5 xl:grid-cols-6">
                <a href="{{ request()->fullUrlWithQuery(['status' => null]) }}"
                   class="rounded-lg border bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:bg-slate-900 {{ ($statusFilter ?? '') === '' ? 'border-slate-900 ring-1 ring-slate-900 dark:border-white dark:ring-white' : 'border-slate-200 dark:border-slate-800' }}">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total</p>
                    <p class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl dark:text-white">{{ number_format($statusCounts->sum(), 0, ',', '.') }}</p>
                </a>
                @foreach($statusCounts as $statusLabel => $qtd)
                    @php $classe = $statusCardClasses[$statusLabel] ?? 'border-slate-200 bg-white text-slate-800 dark:border-slate-800 dark:bg-slate-900'; @endphp
                    <a href="{{ request()->fullUrlWithQuery(['status' => $statusLabel]) }}"
                       class="rounded-lg border p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md {{ $classe }} {{ ($statusFilter ?? '') === $statusLabel ? 'ring-2 ring-current ring-offset-1' : '' }}">
                        <p class="truncate text-xs font-semibold uppercase tracking-wide opacity-80" title="{{ $statusLabel }}">{{ $statusLabel }}</p>
                        <p class="mt-1 text-2xl font-bold">{{ number_format($qtd, 0, ',', '.') }}</p>
                    </a>
                @endforeach
            </section>
        @endif

        <section x-data="bancadaLabelPrinter()" class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            @php
                $resetRoute = match($scope) {
                    'entregues' => 'bancada-servicos.assets.delivered',
                    'descartados' => 'bancada-servicos.assets.discarded',
                    'backup' => 'bancada-servicos.assets.backup',
                    default => 'bancada-servicos.assets',
                };
            @endphp
            @php
                $showTicColumn = $scope === 'equipamentos';
            @endphp
            <div class="border-b border-slate-200 p-5 dark:border-slate-800">
                <form method="GET" class="bancada-filtros flex flex-wrap items-center gap-2 lg:flex-nowrap">
                    <select name="origem_tipo" class="h-10 w-[150px] min-w-[150px] rounded-md border-slate-300 text-sm shrink-0">
                        <option value="">Todas as origens</option>
                        <option value="unidade" @selected(($origemTipo ?? '') === 'unidade')>Unidade</option>
                        <option value="sede" @selected(($origemTipo ?? '') === 'sede')>Sede</option>
                    </select>
                    <input name="q" value="{{ $search }}" placeholder="{{ $showTicColumn ? 'Buscar plaqueta, unidade, TIC...' : 'Buscar plaqueta...' }}" class="h-10 min-w-[280px] flex-1 rounded-md border-slate-300 text-sm">
                    <input name="plaqueta" value="{{ $plaqueta ?? '' }}" placeholder="Plaqueta" class="h-10 w-[130px] min-w-[130px] rounded-md border-slate-300 text-sm shrink-0">
                    <input type="date" name="data_chegada" value="{{ $dataChegada ?? '' }}" class="h-10 w-[150px] min-w-[150px] rounded-md border-slate-300 text-sm shrink-0">
                    <select name="sort_order" class="h-10 w-[170px] min-w-[170px] rounded-md border-slate-300 text-sm shrink-0">
                        <option value="newest" @selected(($sortOrder ?? 'newest') === 'newest')>Mais novos primeiro</option>
                        <option value="oldest" @selected(($sortOrder ?? 'newest') === 'oldest')>Mais antigos primeiro</option>
                    </select>
                    <div class="flex h-10 shrink-0 items-center gap-2">
                        <button type="submit" style="background-color: #033151; border-color: #033151;" class="inline-flex h-10 items-center justify-center rounded-lg px-4 text-sm font-semibold text-white hover:opacity-90">Buscar</button>
                        <a href="{{ route($resetRoute) }}" class="inline-flex h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Limpar</a>
                    </div>
                </form>
            </div>
            <div class="w-full max-w-full overflow-x-auto">
                <table class="{{ $isBackup ? 'min-w-[1650px]' : 'min-w-[1200px]' }} w-full text-sm">
                    <thead class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="px-3 py-3">Tipo</th>
                            <th class="px-3 py-3">Plaqueta</th>
                            <th class="px-3 py-3">Origem</th>
                            <th class="px-3 py-3">Unidade/Setor</th>
                            <th class="px-3 py-3">Chegada</th>
                            <th class="px-3 py-3">Saída</th>
                            @if(! $isBackup)
                                <th class="px-3 py-3">Entrada fiscal</th>
                            @endif
                            <th class="px-3 py-3">Status</th>
                            @if($showTicColumn)
                                <th class="px-3 py-3">TIC</th>
                            @endif
                            @if($isBackup)
                                <th class="px-3 py-3">Localização backup</th>
                                <th class="px-3 py-3">Emprestado para</th>
                                <th class="px-3 py-3">Data backup</th>
                            @endif
                            <th class="px-3 py-3 {{ $isBackup ? 'w-[230px]' : 'w-[200px]' }}">Observação</th>
                            <th class="sticky right-0 z-20 min-w-[200px] bg-white px-3 py-3 shadow-[-8px_0_12px_-12px_rgba(15,23,42,0.25)]">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($assets as $asset)
                            <tr class="odd:bg-slate-50 even:bg-white dark:odd:bg-slate-950 dark:even:bg-slate-900">
                                <td class="px-3 py-3 break-words">{{ $asset->tipo_equipamento }}</td>
                                <td class="px-3 py-3 break-words">{{ $asset->plaqueta }}</td>
                                <td class="px-3 py-3 break-words">{{ $asset->origem_tipo === 'sede' ? 'Sede' : 'Unidade' }}</td>
                                <td class="px-3 py-3 break-words">
                                    {{ $asset->unidade_setor }}
                                </td>
                                <td class="px-3 py-3">{{ optional($asset->data_chegada)->format('d/m/Y') }}</td>
                                <td class="px-3 py-3">{{ optional($asset->data_saida)->format('d/m/Y') ?: '-' }}</td>
                                @if(! $isBackup)
                                    <td class="px-3 py-3" x-data="{ editModalOpen: false, editOrigem: @js(old('origem_tipo', $asset->origem_tipo ?: 'unidade')) }">
                                        @if(in_array($asset->entrada_status, ['Aguardando Entrada', 'Aguardando Entrada Fiscal'], true))
                                            <span class="inline-flex rounded-md bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800">Aguardando Entrada Fiscal</span>
                                        @else
                                            <span class="inline-flex rounded-md bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800">Entrada Realizada</span>
                                        @endif
                                    </td>
                                @endif
                                <td class="px-3 py-3" x-data="{ nextStatus: '', pecaModalOpen: false, terceirosModalOpen: false }">
                                    @php
                                        $statusNorm = str_replace(['Aguardando Entrada', 'Em Bancada'], ['Aguardando Entrada Fiscal', 'Em bancada'], (string) $asset->status);
                                        $entryPending = in_array((string) $asset->entrada_status, ['Aguardando Entrada', 'Aguardando Entrada Fiscal'], true);
                                        $statusBadgeLabel = null;
                                        $statusBadgeDescription = null;
                                        $statusBadgeClass = 'bg-slate-100 text-slate-700';
                                        $statusLockMessage = null;
                                        $statusLockBadgeLabel = null;
                                        $statusLockBadgeDescription = null;
                                        $statusLockBadgeClass = 'bg-rose-100 text-rose-800';

                                        if ($scope === 'descartados') {
                                            $statusBadgeLabel = $asset->baixa_realizada ? 'Baixa realizada' : 'Baixa pendente';
                                            $statusBadgeDescription = 'Descarte';
                                            $statusBadgeClass = $asset->baixa_realizada
                                                ? 'bg-emerald-100 text-emerald-800'
                                                : 'bg-amber-100 text-amber-800';
                                            $statusLockMessage = 'Somente consulta';
                                        } elseif ($scope === 'backup') {
                                            $statusBadgeLabel = $asset->backup_pronto_emprestimo ? 'Disponível' : 'Indisponível';
                                            $statusBadgeDescription = 'Backup';
                                            $statusBadgeClass = $asset->backup_pronto_emprestimo
                                                ? 'bg-emerald-100 text-emerald-800'
                                                : 'bg-amber-100 text-amber-800';
                                            $statusLockMessage = 'Somente consulta';
                                        } elseif ($statusNorm === 'Terceiros') {
                                            $thirdPartyStage = $asset->thirdPartyWorkflowStage();

                                            if ($thirdPartyStage === 'aguardando_informacoes') {
                                                $statusLockBadgeLabel = 'Aguardando terceiro';
                                                $statusLockBadgeDescription = 'Enviado / aguardando informações';
                                                $statusLockBadgeClass = 'bg-amber-100 text-amber-800';
                                                $statusLockMessage = 'Aguardando informações do terceiro';
                                            } elseif ($thirdPartyStage === 'aguardando_retorno_fisico') {
                                                $statusLockBadgeLabel = 'Aguardando terceiro';
                                                $statusLockBadgeDescription = 'Informações recebidas / aguardando retorno físico';
                                                $statusLockBadgeClass = 'bg-blue-100 text-blue-800';
                                                $statusLockMessage = 'Aguardando retorno físico do terceiro';
                                            } else {
                                                $statusLockBadgeLabel = 'Pendente ADM';
                                                $statusLockBadgeDescription = 'Aguardando envio ao terceiro';
                                                $statusLockBadgeClass = 'bg-rose-100 text-rose-800';
                                                $statusLockMessage = 'Aguardando ação do Administrativo';
                                            }
                                        } elseif ($statusNorm === 'Aguardando peça') {
                                            if (($asset->peca_origem ?? '') === 'estoque_ti') {
                                                $statusBadgeLabel = 'Estoque Interno';
                                                $statusBadgeDescription = 'TI';
                                                $statusBadgeClass = 'bg-emerald-100 text-emerald-800';
                                                $statusLockMessage = 'Somente consulta';
                                            } elseif (($asset->peca_fluxo_status ?? '') === 'requisicao_realizada') {
                                                $statusBadgeLabel = 'Ação Bancada';
                                                $statusBadgeDescription = 'Confirmar recebimento da peça';
                                                $statusBadgeClass = 'bg-indigo-100 text-indigo-800';
                                                $statusLockMessage = 'Confirmar recebimento da peça';
                                            } else {
                                                $statusBadgeLabel = 'Pendente ADM';
                                                $statusBadgeDescription = 'Aguardando ação do Administrativo';
                                                $statusBadgeClass = 'bg-rose-100 text-rose-800';
                                                $statusLockMessage = 'Aguardando ação do Administrativo';
                                            }
                                        } elseif ($statusNorm === 'Pronto para entrega') {
                                            if (! app(\App\Services\BancadaStatusFlowService::class)->requiresFiscalFlow($asset)) {
                                                $statusBadgeLabel = 'Pronto';
                                                $statusBadgeDescription = 'Liberado para entrega';
                                                $statusBadgeClass = 'bg-emerald-100 text-emerald-800';
                                            } else {
                                                $statusBadgeLabel = 'Pendente ADM';
                                                $statusBadgeDescription = 'Aguardando nota de saída';
                                                $statusBadgeClass = 'bg-rose-100 text-rose-800';
                                                $statusLockMessage = 'Aguardando nota de saída pelo Administrativo';
                                            }
                                        } elseif ($statusNorm === 'Nota Fiscal Emitida') {
                                            $statusBadgeLabel = 'Pronto';
                                            $statusBadgeDescription = 'Liberado para entrega';
                                            $statusBadgeClass = 'bg-emerald-100 text-emerald-800';
                                        } elseif ($statusNorm === 'Sem conserto') {
                                            $statusBadgeLabel = 'Ação Bancada';
                                            $statusBadgeDescription = 'Enviar para descarte';
                                            $statusBadgeClass = 'bg-indigo-100 text-indigo-800';
                                            $statusLockMessage = 'Enviar para descarte';
                                        }

                                        $transitions = $availableTransitions[$asset->id] ?? [];
                                        $blockedByAdm = empty($transitions);
                                        if ($statusNorm === 'Aguardando Entrada Fiscal' || $entryPending) {
                                            $statusLockMessage = 'Aguardando entrada fiscal pelo Administrativo';
                                        } elseif ($statusNorm === 'Entregue' || $statusNorm === 'Descarte' || $statusNorm === 'Backup') {
                                            $statusLockMessage = 'Somente consulta';
                                        } elseif ($statusLockMessage === null) {
                                            $statusLockMessage = 'Aguardando ação do Administrativo';
                                        }

                                        $currentStatusDisplay = str_replace(
                                            ['Aguardando Entrada', 'Em Bancada'],
                                            ['Aguardando Entrada Fiscal', 'Em bancada'],
                                            (string) $asset->status
                                        );
                                    @endphp
                                    @if($statusBadgeLabel && ! $blockedByAdm && ! in_array($scope, ['descartados', 'backup'], true))
                                        <div class="mb-2 inline-flex flex-col items-start gap-0.5">
                                            <span class="inline-flex rounded-md px-2 py-0.5 text-[11px] font-semibold {{ $statusBadgeClass }}">
                                                {{ $statusBadgeLabel }}
                                            </span>
                                            @if($statusBadgeDescription)
                                                <span class="text-[10px] font-medium text-slate-500">{{ $statusBadgeDescription }}</span>
                                            @endif
                                        </div>
                                    @endif
                                    <div>
                                    @if($scope === 'descartados')
                                        <div class="inline-flex flex-col items-start gap-0.5">
                                            <span class="inline-flex rounded-md px-2 py-0.5 text-[11px] font-semibold {{ $statusBadgeClass }}">{{ $statusBadgeLabel }}</span>
                                            <span class="text-[10px] font-medium text-slate-500">{{ $statusBadgeDescription }}</span>
                                        </div>
                                    @elseif($scope === 'backup')
                                        <div class="inline-flex flex-col items-start gap-0.5">
                                            <span class="inline-flex rounded-md px-2 py-0.5 text-[11px] font-semibold {{ $statusBadgeClass }}">{{ $statusBadgeLabel }}</span>
                                            <span class="text-[10px] font-medium text-slate-500">{{ $statusBadgeDescription }}</span>
                                        </div>
                                    @elseif($blockedByAdm)
                                        <div class="inline-flex flex-col items-start gap-0.5" title="{{ $statusLockMessage }}">
                                            <span class="inline-flex rounded-md px-2 py-0.5 text-[11px] font-semibold {{ $statusLockBadgeClass }}">
                                                {{ $statusLockBadgeLabel ?? 'Pendente ADM' }}
                                            </span>
                                            <span class="text-[10px] font-medium text-slate-500">{{ $statusLockBadgeDescription ?? $statusLockMessage }}</span>
                                        </div>
                                    @else
                                    <form method="POST" action="{{ route('bancada-servicos.assets.status', $asset) }}" class="flex flex-wrap items-center gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <select name="status" x-model="nextStatus" required class="max-w-full rounded-md border-slate-300 text-xs">
                                            <option value="" selected disabled>{{ $currentStatusDisplay !== '' ? $currentStatusDisplay : 'Sem status' }}</option>
                                            @foreach($transitions as $option)
                                                <option value="{{ $option }}">{{ $option }}</option>
                                            @endforeach
                                        </select>
                                        <input type="hidden" name="confirm_peca_recebida" value="0">
                                        <button
                                            x-show="nextStatus && nextStatus !== 'Aguardando peça' && nextStatus !== 'Terceiros'"
                                            @click="
                                                if (nextStatus === 'Manutenção realizada' && @js($asset->status) === 'Aguardando peça' && @js($asset->peca_origem) !== 'estoque_ti') {
                                                    $event.target.form.querySelector('input[name=confirm_peca_recebida]').value = '1';
                                                } else {
                                                    $event.target.form.querySelector('input[name=confirm_peca_recebida]').value = '0';
                                                }
                                            "
                                            class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                        >Atualizar</button>
                                        <button type="button" x-show="nextStatus === 'Aguardando peça'" @click="pecaModalOpen = true" class="rounded-lg border border-[#033151] bg-[#033151] px-2 py-1 text-xs font-semibold text-white hover:opacity-90">Continuar</button>
                                        <button type="button" x-show="nextStatus === 'Terceiros'" @click="terceirosModalOpen = true" class="rounded-lg border border-[#033151] bg-[#033151] px-2 py-1 text-xs font-semibold text-white hover:opacity-90">Continuar</button>
                                    </form>
                                    @endif
                                    </div>
                                    <div x-cloak x-show="pecaModalOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 px-4" @keydown.escape.window="pecaModalOpen=false">
                                        <div class="w-full max-w-3xl overflow-hidden rounded-lg bg-white shadow-2xl" @click.outside="pecaModalOpen=false">
                                            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                                                <h3 class="text-lg font-bold text-slate-900">Aguardando peça | {{ $asset->plaqueta }}</h3>
                                                <button type="button" @click="pecaModalOpen=false" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Fechar</button>
                                            </div>
                                            <form x-data="{ origem: '', peca: '', produtos: @js($stockProducts ?? []), get saldoSel(){ const p = this.produtos.find(x => x.nome === this.peca); return p ? p.saldo : null } }" method="POST" action="{{ route('bancada-servicos.assets.status', $asset) }}" class="grid gap-3 px-5 py-4 sm:grid-cols-2">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="status" value="Aguardando peça">
                                                <div class="sm:col-span-2">
                                                    <label class="text-sm font-medium text-slate-700">Unidade/Setor</label>
                                                    <div class="mt-1 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ $asset->unidade_setor }}</div>
                                                </div>
                                                <div class="sm:col-span-2">
                                                    <label class="text-sm font-medium text-slate-700">Origem da peça</label>
                                                    <select name="peca_origem" x-model="origem" @change="peca=''" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                                        <option value="">Selecione</option>
                                                        <option value="cd">Estoque do Centro de Distribuição</option>
                                                        <option value="compra_internet">Compra pela internet</option>
                                                        <option value="estoque_ti">Estoque interno da TI</option>
                                                        <option value="dell">Fornecedor Dell</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="text-sm font-medium text-slate-700">Peça necessária</label>
                                                    {{-- Estoque interno: escolhe entre as peças cadastradas (com saldo). --}}
                                                    <select x-show="origem === 'estoque_ti'" x-cloak name="peca_nome" x-model="peca" :disabled="origem !== 'estoque_ti'" :required="origem === 'estoque_ti'" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                                        <option value="">Selecione a peça do estoque</option>
                                                        <template x-for="p in produtos" :key="p.nome">
                                                            <option :value="p.nome" :disabled="p.saldo <= 0" x-text="p.nome + ' (' + p.saldo + ' em estoque)'"></option>
                                                        </template>
                                                    </select>
                                                    {{-- Demais origens: texto livre, como antes. --}}
                                                    <input x-show="origem !== 'estoque_ti'" name="peca_nome" :disabled="origem === 'estoque_ti'" :required="origem !== 'estoque_ti'" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                                    <p x-show="origem === 'estoque_ti' && peca" x-cloak class="mt-1 text-xs text-slate-500">Saldo disponível: <span x-text="saldoSel"></span> un</p>
                                                </div>
                                                <div>
                                                    <label class="text-sm font-medium text-slate-700">Quantidade</label>
                                                    <input type="number" min="1" :max="origem === 'estoque_ti' && saldoSel !== null ? saldoSel : 999" name="peca_quantidade" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                                </div>
                                                <div x-show="origem === 'dell'" x-cloak>
                                                    <label class="text-sm font-medium text-slate-700">ServiceTag (Dell)</label>
                                                    <input name="service_tag" :required="origem === 'dell'" :disabled="origem !== 'dell'" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                                </div>
                                                <div class="sm:col-span-2" x-show="origem === 'compra_internet'" x-cloak>
                                                    <label class="text-sm font-medium text-slate-700">Link da compra (internet)</label>
                                                    <input name="peca_link_compra" :required="origem === 'compra_internet'" :disabled="origem !== 'compra_internet'" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                                </div>
                                                <div class="sm:col-span-2 flex justify-end gap-2 border-t border-slate-200 pt-3">
                                                    <button type="button" @click="pecaModalOpen=false" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</button>
                                                    <button class="inline-flex min-h-10 items-center justify-center rounded-lg border border-[#033151] bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Salvar e enviar ao Administrativo</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                    <div x-cloak x-show="terceirosModalOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 px-4" @keydown.escape.window="terceirosModalOpen=false">
                                        <div class="w-full max-w-2xl overflow-hidden rounded-lg bg-white shadow-2xl" @click.outside="terceirosModalOpen=false">
                                            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                                                <h3 class="text-lg font-bold text-slate-900">Enviar para Terceiros | {{ $asset->plaqueta }}</h3>
                                                <button type="button" @click="terceirosModalOpen=false" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Fechar</button>
                                            </div>
                                            <form method="POST" action="{{ route('bancada-servicos.assets.status', $asset) }}" class="grid gap-3 px-5 py-4">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="status" value="Terceiros">
                                                <div>
                                                    <label class="text-sm font-medium text-slate-700">Descrição do problema</label>
                                                    <textarea name="terceiros_problema" rows="3" required class="mt-1 w-full rounded-md border-slate-300 text-sm"></textarea>
                                                </div>
                                                <div class="flex justify-end gap-2 border-t border-slate-200 pt-3">
                                                    <button type="button" @click="terceirosModalOpen=false" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</button>
                                                    <button class="inline-flex min-h-10 items-center justify-center rounded-lg border border-[#033151] bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Salvar e enviar ao Administrativo</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                    @if($asset->status === 'Aguardando peça')
                                        <div class="mt-2 rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-xs text-slate-700">
                                            <p><strong>Peça:</strong> {{ $asset->peca_nome ?: '-' }} ({{ $asset->peca_quantidade ?: '-' }})</p>
                                            <p><strong>Origem:</strong> {{ $asset->peca_origem ?: '-' }}</p>
                                        </div>
                                    @endif
                                    @if($statusNorm === 'Aguardando peça' && ($asset->peca_origem ?? '') !== 'estoque_ti' && ($asset->peca_fluxo_status ?? '') === 'requisicao_realizada')
                                        <form method="POST" action="{{ route('bancada-servicos.assets.status', $asset) }}" class="mt-2">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="Manutenção realizada">
                                            <input type="hidden" name="confirm_peca_recebida" value="1">
                                            <button class="rounded-lg border border-[#033151] bg-[#033151] px-2 py-1 text-xs font-semibold text-white hover:opacity-90">Confirmar peça recebida</button>
                                        </form>
                                    @endif
                                    @if($statusNorm === 'Sem conserto')
                                        <form method="POST" action="{{ route('bancada-servicos.assets.status', $asset) }}" class="mt-2">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="Descarte">
                                            <button class="rounded-lg border border-[#033151] bg-[#033151] px-2 py-1 text-xs font-semibold text-white hover:opacity-90">Enviar para descarte</button>
                                        </form>
                                    @endif
                                    @if($statusNorm === 'Manutenção realizada')
                                        <form method="POST" action="{{ route('bancada-servicos.assets.status', $asset) }}" class="mt-2">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="Pronto para entrega">
                                            <button class="rounded-lg border border-[#033151] bg-[#033151] px-2 py-1 text-xs font-semibold text-white hover:opacity-90">Enviar para Pronto para entrega</button>
                                        </form>
                                    @endif
                                </td>
                                @if($showTicColumn)
                                    <td class="px-3 py-3 break-words">
                                        @if($asset->tic)
                                            <a href="{{ $jiraTicketBaseUrl . '/' . rawurlencode((string) $asset->tic) }}" target="_blank" class="text-blue-600 hover:text-blue-800">{{ $asset->tic }}</a>
                                        @else - @endif
                                    </td>
                                @endif
                                @if($isBackup)
                                    <td class="px-3 py-3 break-words">{{ $asset->backup_localizacao ?: '-' }}</td>
                                    <td class="px-3 py-3 break-words">{{ $asset->backup_emprestado_para ?: '-' }}</td>
                                    <td class="px-3 py-3">{{ optional($asset->backup_data_formatado)->format('d/m/Y') ?: '-' }}</td>
                                @endif
                                <td class="{{ $isBackup ? 'max-w-[230px]' : 'max-w-[200px]' }} px-3 py-3 align-top break-words" title="{{ $asset->observacao ?: '' }}">
                                    <div style="display:-webkit-box;-webkit-line-clamp:{{ $isBackup ? 3 : 2 }};-webkit-box-orient:vertical;overflow:hidden;">{{ $asset->observacao ?: '-' }}</div>
                                </td>
                                <td class="sticky right-0 z-10 min-w-[200px] bg-white px-3 py-3 align-top shadow-[-8px_0_12px_-12px_rgba(15,23,42,0.65)] group-odd:bg-slate-50 group-even:bg-white" x-data="{ editModalOpen: false, editOrigem: @js($asset->origem_tipo ?: 'unidade'), backupModalOpen: false, loanModalOpen: false }">
                                    @if($scope === 'descartados')
                                        <form method="POST" action="{{ route('bancada-servicos.assets.discard.update', $asset) }}" class="mb-2 space-y-2 rounded-md border border-slate-200 p-2">
                                            @csrf
                                            @method('PATCH')
                                            <label class="flex items-center gap-2 text-xs">
                                                <input type="checkbox" name="baixa_realizada" value="1" @checked($asset->baixa_realizada)>
                                                Baixa realizada
                                            </label>
                                            <button class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">Salvar</button>
                                        </form>
                                    @endif
                                    <div class="mb-2">
                                        @if($scope === 'descartados')
                                        @elseif(in_array($asset->entrada_status, ['Aguardando Entrada', 'Aguardando Entrada Fiscal'], true) && app(\App\Services\BancadaStatusFlowService::class)->requiresFiscalFlow($asset))
                                            <button type="button" disabled class="inline-flex min-h-8 cursor-not-allowed items-center justify-center rounded-md border border-amber-300 bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700 opacity-100" title="Aguardando entrada automática no Administrativo">
                                                Aguardando entrada (automático)
                                            </button>
                                        @else
                                            <button type="button" disabled class="inline-flex min-h-8 cursor-not-allowed items-center justify-center rounded-md border border-slate-300 bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600 opacity-100" title="Entrada já registrada">
                                                Entrada realizada
                                            </button>
                                        @endif
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        @if($isBackup)
                                            <form method="POST" action="{{ route('bancada-servicos.assets.backup.availability.update', $asset) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button class="inline-flex min-h-8 items-center justify-center rounded-md border px-2 py-1 text-xs font-semibold {{ $asset->backup_pronto_emprestimo ? 'border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100' : 'border-emerald-300 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                                                    {{ $asset->backup_pronto_emprestimo ? 'Marcar indisponível' : 'Marcar disponível' }}
                                                </button>
                                            </form>
                                        @endif
                                        @if($scope === 'equipamentos' || $scope === 'backup' || $scope === 'descartados')
                                            <button type="button" @click="editModalOpen = true" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 bg-white text-[#033151] hover:bg-slate-50 hover:text-[#02243e]" title="Editar" aria-label="Editar">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 20h4l10-10-4-4L4 16v4Z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m12 6 4 4" />
                                                </svg>
                                            </button>
                                        @endif
                                        @if($isBackup)
                                            <button type="button" @click="loanModalOpen = true; $nextTick(() => { if ($refs.loanDestination) $refs.loanDestination.value = ''; })" class="inline-flex min-h-8 items-center justify-center rounded-md border border-amber-300 bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-800 hover:bg-amber-100">
                                                Emprestar
                                            </button>
                                            <button type="button" @click="backupModalOpen = true" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 bg-white text-[#033151] hover:bg-slate-50 hover:text-[#02243e]" title="Editar backup" aria-label="Editar backup">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 7h18M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M6 7h12v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V7Z" />
                                                </svg>
                                            </button>
                                        @endif
                                        <a href="{{ route('bancada-servicos.assets.history', $asset) }}" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 bg-white text-[#033151] hover:bg-slate-50 hover:text-[#02243e]" title="Histórico" aria-label="Histórico">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v5l3 2" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12a9 9 0 1 0 3-6.7M3 4v3h3" />
                                            </svg>
                                        </a>
                                        <button type="button" @click="window.dispatchEvent(new CustomEvent('open-documents',{detail:{url: @js(route('bancada-servicos.assets.documents', $asset)), plaqueta: @js($asset->plaqueta)}}))" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 bg-white text-[#033151] hover:bg-slate-50 hover:text-[#02243e]" title="Ver documentos" aria-label="Documentos">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 10v6a2 2 0 0 0 2 2h6" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 15v4a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h4" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 10h10l-4-4" />
                                            </svg>
                                        </button>
                                        <button
                                            type="button"
                                            @click="print(@js($asset->unidade_setor), @js(optional($asset->data_chegada)->format('d/m/Y')), @js($asset->observacao))"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 bg-white text-[#033151] hover:bg-slate-50 hover:text-[#02243e]"
                                            title="Imprimir"
                                            aria-label="Imprimir"
                                        >
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 9V4h12v5M6 14H5a2 2 0 0 1-2-2v-1a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v1a2 2 0 0 1-2 2h-1" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 13h12v7H6z" />
                                            </svg>
                                        </button>
                                    </div>
                                    @if($isBackup)
                                        <template x-teleport="body">
                                        <div x-cloak x-show="loanModalOpen" x-transition.opacity class="fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto bg-slate-900/60" style="width:100vw;min-height:100vh;padding:1rem;box-sizing:border-box;" @keydown.escape.window="loanModalOpen=false">
                                            <div class="my-auto overflow-hidden rounded-lg bg-white shadow-2xl" style="width:min(100%, 32rem);max-width:32rem;max-height:calc(100vh - 2rem);box-sizing:border-box;" @click.outside="loanModalOpen=false">
                                                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                                                    <h3 class="text-lg font-bold text-slate-900">Emprestar equipamento | {{ $asset->plaqueta }}</h3>
                                                    <button type="button" @click="loanModalOpen=false" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Fechar</button>
                                                </div>
                                                <form method="POST" action="{{ route('bancada-servicos.assets.backup.loan', $asset) }}" class="space-y-4 px-5 py-4">
                                                    @csrf
                                                    @method('PATCH')
                                                    <div>
                                                        <label class="text-sm font-medium text-slate-700">Para onde foi emprestado?</label>
                                                        <select x-ref="loanDestination" name="backup_emprestado_para" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                                            <option value="" selected>Selecione uma unidade ou departamento</option>
                                                            <optgroup label="Unidades">
                                                                @foreach(($unitOptionsByOrigin['unidade'] ?? []) as $unitOption)
                                                                    <option value="{{ $unitOption }}">{{ $unitOption }}</option>
                                                                @endforeach
                                                            </optgroup>
                                                            <optgroup label="Departamentos">
                                                                @foreach(($unitOptionsByOrigin['sede'] ?? []) as $departmentOption)
                                                                    <option value="{{ $departmentOption }}">{{ $departmentOption }}</option>
                                                                @endforeach
                                                            </optgroup>
                                                        </select>
                                                    </div>
                                                    <div class="flex justify-end gap-2 border-t border-slate-200 pt-3">
                                                        <button type="button" @click="loanModalOpen=false" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</button>
                                                        <button class="inline-flex min-h-10 items-center justify-center rounded-lg border border-[#033151] bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Registrar empréstimo</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                        </template>
                                    @endif
                                    @if($isBackup)
                                        <div x-cloak x-show="backupModalOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 px-4" @keydown.escape.window="backupModalOpen=false">
                                            <div class="w-full max-w-3xl overflow-hidden rounded-lg bg-white shadow-2xl" @click.outside="backupModalOpen=false">
                                                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                                                    <h3 class="text-lg font-bold text-slate-900">Editar backup | {{ $asset->plaqueta }}</h3>
                                                    <button type="button" @click="backupModalOpen=false" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Fechar</button>
                                                </div>
                                                <form method="POST" action="{{ route('bancada-servicos.assets.backup.update', $asset) }}" class="grid gap-3 px-5 py-4 sm:grid-cols-2">
                                                    @csrf
                                                    @method('PATCH')
                                                    <div class="sm:col-span-2">
                                                        <label class="text-sm font-medium text-slate-700">Localização atual</label>
                                                        <input name="backup_localizacao" value="{{ $asset->backup_localizacao }}" placeholder="TI / Unidade / Setor" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                                    </div>
                                                    <div>
                                                        <label class="text-sm font-medium text-slate-700">Data backup</label>
                                                        <input type="date" name="backup_data_formatado" value="{{ optional($asset->backup_data_formatado)->format('Y-m-d') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                                    </div>
                                                    <div class="flex items-end">
                                                        <label class="inline-flex items-center gap-2 rounded-md border border-slate-300 px-3 py-2 text-sm">
                                                            <input type="checkbox" name="backup_pronto_emprestimo" value="1" @checked($asset->backup_pronto_emprestimo)>
                                                            Pronto para empréstimo novamente
                                                        </label>
                                                    </div>
                                                    <div class="sm:col-span-2">
                                                        <label class="text-sm font-medium text-slate-700">Observação de backup</label>
                                                        <textarea name="observacao" rows="2" class="mt-1 w-full rounded-md border-slate-300 text-sm">{{ $asset->observacao }}</textarea>
                                                    </div>
                                                    <div class="sm:col-span-2 flex justify-end gap-2 border-t border-slate-200 pt-3">
                                                    <button type="button" @click="backupModalOpen=false" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</button>
                                                    <button class="inline-flex min-h-10 items-center justify-center rounded-lg border border-[#033151] bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Salvar backup</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    @endif
                                    <div x-cloak x-show="editModalOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 px-4" @keydown.escape.window="editModalOpen=false">
                                        <div class="w-full max-w-3xl overflow-hidden rounded-lg bg-white shadow-2xl" @click.outside="editModalOpen=false">
                                            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                                                <h3 class="text-lg font-bold text-slate-900">Editar equipamento</h3>
                                                <button type="button" @click="editModalOpen=false" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Fechar</button>
                                            </div>
                                            <form method="POST" action="{{ route('bancada-servicos.assets.update', $asset) }}" class="grid gap-3 px-5 py-4 sm:grid-cols-2">
                                                @csrf
                                                @method('PUT')
                                                <div>
                                                    <label class="text-sm font-medium text-slate-700">Tipo do equipamento</label>
                                                    <select name="tipo_equipamento" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                                        <option value="">Selecione</option>
                                                        @foreach(($equipmentTypeOptions ?? []) as $typeOption)
                                                            <option value="{{ $typeOption }}" @selected($asset->tipo_equipamento === $typeOption)>{{ $typeOption }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="text-sm font-medium text-slate-700">Plaqueta</label>
                                                    <input name="plaqueta" value="{{ $asset->plaqueta }}" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                                </div>
                                                <div>
                                                    <label class="text-sm font-medium text-slate-700">Origem</label>
                                                    <select name="origem_tipo" x-model="editOrigem" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                                        <option value="unidade">Unidade</option>
                                                        <option value="sede">Departamento da sede</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="text-sm font-medium text-slate-700">Unidade/Setor</label>
                                                    <select x-show="editOrigem === 'unidade'" x-cloak name="unidade_setor" :required="editOrigem === 'unidade'" :disabled="editOrigem !== 'unidade'" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                                        <option value="">Selecione a unidade</option>
                                                        @foreach(($unitOptionsByOrigin['unidade'] ?? []) as $unitOption)
                                                            <option value="{{ $unitOption }}" @selected($asset->unidade_setor === $unitOption)>{{ $unitOption }}</option>
                                                        @endforeach
                                                    </select>
                                                    <select x-show="editOrigem === 'sede'" x-cloak name="unidade_setor" :required="editOrigem === 'sede'" :disabled="editOrigem !== 'sede'" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                                        <option value="">Selecione o departamento</option>
                                                        @foreach(($unitOptionsByOrigin['sede'] ?? []) as $unitOption)
                                                            <option value="{{ $unitOption }}" @selected($asset->unidade_setor === $unitOption)>{{ $unitOption }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="text-sm font-medium text-slate-700">TIC</label>
                                                    <input name="tic" value="{{ $asset->tic }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                                </div>
                                                <div class="sm:col-span-2">
                                                    <label class="text-sm font-medium text-slate-700">Observação</label>
                                                    <textarea name="observacao" rows="3" class="mt-1 w-full rounded-md border-slate-300 text-sm">{{ $asset->observacao }}</textarea>
                                                </div>
                                                <div class="sm:col-span-2 flex justify-end gap-2 border-t border-slate-200 pt-3">
                                                    <button type="button" @click="editModalOpen=false" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</button>
                                                    <button style="background-color: #033151; border-color: #033151;" class="inline-flex min-h-10 items-center justify-center rounded-lg px-4 text-sm font-semibold text-white hover:opacity-90">Salvar alterações</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $showTicColumn ? ($isBackup ? 14 : 11) : ($isBackup ? 13 : 10) }}" class="px-4 py-6 text-center text-slate-500">Nenhum equipamento encontrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="flex flex-col gap-3 border-t border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                <form method="GET" class="flex items-center gap-2 text-sm text-slate-600">
                    <span>Itens por página:</span>
                    <input type="hidden" name="q" value="{{ $search }}">
                    <input type="hidden" name="tipo" value="{{ $tipo ?? '' }}">
                    <input type="hidden" name="unidade_setor" value="{{ $unidadeSetor ?? '' }}">
                    <input type="hidden" name="origem_tipo" value="{{ $origemTipo ?? '' }}">
                    <input type="hidden" name="plaqueta" value="{{ $plaqueta ?? '' }}">
                    <input type="hidden" name="data_chegada" value="{{ $dataChegada ?? '' }}">
                    <select name="per_page" onchange="this.form.submit()" class="toolbar-select text-sm">
                        <option value="10" @selected($perPage === 10)>10</option>
                        <option value="25" @selected($perPage === 25)>25</option>
                        <option value="50" @selected($perPage === 50)>50</option>
                    </select>
                </form>
                {{ $assets->links() }}
            </div>
        </section>
        <div
            x-cloak
            x-show="addModalOpen"
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 px-4"
            @keydown.escape.window="addModalOpen = false; $refs.addEquipmentForm.reset()"
        >
            <div class="w-full max-w-6xl overflow-hidden rounded-lg bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                    <h2 class="text-2xl font-bold text-slate-900">Adicionar equipamento</h2>
                    <button type="button" @click="addModalOpen = false; $refs.addEquipmentForm.reset()" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Fechar</button>
                </div>
                <form
                    x-ref="addEquipmentForm"
                    method="POST"
                    enctype="multipart/form-data"
                    action="{{ route('bancada-servicos.assets.store') }}"
                    class="max-h-[78vh] overflow-y-auto px-6 py-5"
                    x-data="{ origem: @js(old('origem_tipo', 'unidade')) }"
                >
                    @csrf
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <label class="text-sm font-medium text-slate-700">Tipo do equipamento</label>
                            <select name="tipo_equipamento" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                <option value="">Selecione</option>
                                @foreach(($equipmentTypeOptions ?? []) as $typeOption)
                                    <option value="{{ $typeOption }}" @selected(old('tipo_equipamento') === $typeOption)>{{ $typeOption }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Plaqueta</label>
                            <input name="plaqueta" value="{{ old('plaqueta') }}" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Data chegada</label>
                            <input type="date" name="data_chegada" value="{{ old('data_chegada', now()->toDateString()) }}" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Origem</label>
                            <select name="origem_tipo" x-model="origem" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                <option value="unidade" @selected(old('origem_tipo', 'unidade') === 'unidade')>Unidade</option>
                                <option value="sede" @selected(old('origem_tipo') === 'sede')>Departamento da sede</option>
                            </select>
                        </div>
                        <div class="lg:col-span-2">
                            <label class="text-sm font-medium text-slate-700">Unidade/Setor</label>
                            <select x-show="origem === 'unidade'" x-cloak name="unidade_setor" :required="origem === 'unidade'" :disabled="origem !== 'unidade'" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                <option value="">Selecione a unidade</option>
                                @foreach(($unitOptionsByOrigin['unidade'] ?? []) as $unitOption)
                                    <option value="{{ $unitOption }}" @selected(old('unidade_setor') === $unitOption)>{{ $unitOption }}</option>
                                @endforeach
                            </select>
                            <select x-show="origem === 'sede'" x-cloak name="unidade_setor" :required="origem === 'sede'" :disabled="origem !== 'sede'" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                <option value="">Selecione o departamento</option>
                                @foreach(($unitOptionsByOrigin['sede'] ?? []) as $unitOption)
                                    <option value="{{ $unitOption }}" @selected(old('unidade_setor') === $unitOption)>{{ $unitOption }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="lg:col-span-3">
                            <label class="text-sm font-medium text-slate-700">Observação</label>
                            <textarea name="observacao" rows="2" class="mt-1 w-full rounded-md border-slate-300 text-sm">{{ old('observacao') }}</textarea>
                        </div>
                    </div>
                    <div class="mt-5 grid gap-3 border-t border-slate-200 pt-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <label class="text-sm font-medium text-slate-700">TIC (opcional)</label>
                            <input name="tic" value="{{ old('tic') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        </div>
                    </div>
                    <div class="mt-5 flex items-center justify-end gap-2 border-t border-slate-200 pt-4">
                        <button type="button" @click="addModalOpen = false; $refs.addEquipmentForm.reset()" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700">Cancelar</button>
                        <button style="background-color: #033151; border-color: #033151;" class="inline-flex min-h-10 items-center justify-center rounded-lg px-4 text-sm font-semibold text-white hover:opacity-90">Adicionar equipamento</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @include('bancada-servicos.partials.documents-modal')
</x-app-layout>
