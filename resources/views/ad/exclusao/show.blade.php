<x-app-layout>
    {{--
        A seleção consulta pelo id do formulário, e não por this.$el.

        Dentro de um método do Alpine chamado por evento, $el aponta para o
        elemento que disparou — a própria caixa clicada, que não tem filhos.
        Era por isso que "Marcar todas" não marcava nada, embora o contador
        inicial funcionasse: no init() o $el ainda é a raiz do componente.

        O comentário fica aqui, e não dentro do atributo, para não depender de
        como o Alpine avalia a expressão.
    --}}
    <div class="mx-auto max-w-7xl space-y-6"
         x-data="{
            marcados: 0,
            caixas() { return document.querySelectorAll('#form-aprovar .item-exclusao') },
            marcadas() { return document.querySelectorAll('#form-aprovar .item-exclusao:checked') },
            init() { this.$nextTick(() => this.recontar()) },
            recontar() {
                this.marcados = this.marcadas().length;
                const cabecalho = document.getElementById('marcar-todas');
                if (! cabecalho) return;
                const total = this.caixas().length;
                cabecalho.checked = total > 0 && this.marcados === total;
                cabecalho.indeterminate = this.marcados > 0 && this.marcados < total;
            },
            marcarTodas(valor) {
                this.caixas().forEach(c => { c.checked = valor });
                this.recontar();
            }
         }">

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <a href="{{ route('ad.exclusao.index') }}" class="text-xs text-slate-500 hover:text-slate-700">&larr; Lotes de exclusão</a>
                <h1 class="mt-1 text-xl font-bold text-slate-950">Exclusão de contas — {{ $lote->referencia }}</h1>
                <p class="text-sm text-slate-500">
                    Contas desativadas há mais de {{ $lote->dias_criterio }} dias
                    @if ($lote->origem_host) · enviado por {{ $lote->origem_host }} @endif
                </p>
            </div>
            <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium ring-1 ring-inset {{ $lote->classesBadge() }}">
                {{ $lote->rotulo() }}
            </span>
        </div>

        @if (session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ session('error') }}</div>
        @endif

        @if ($lote->status === \App\Models\AdPurgeBatch::STATUS_APROVADO)
            <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                <p class="font-semibold">Aprovado — aguardando execução no servidor do AD.</p>
                <p class="mt-1">
                    O servidor busca as contas aprovadas na próxima verificação e exclui em lotes de até
                    {{ config('ad.expurgo.max_por_execucao', 200) }} por vez, para não sobrecarregar a replicação
                    entre os controladores.
                    @if ($lote->expira_em)
                        Esta aprovação vale até <strong>{{ $lote->expira_em->local()->format('d/m/Y H:i') }}</strong>; depois disso o lote expira e nada é excluído.
                    @endif
                </p>
            </div>
        @endif

        @if ($lote->status === \App\Models\AdPurgeBatch::STATUS_EXPIRADO)
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                A aprovação venceu antes de ser executada. Nenhuma conta foi excluída por este lote.
                A lista foi conferida em uma data específica e as contas mudam — para seguir, é preciso uma prévia nova.
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-4">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Candidatas</p>
                <p class="mt-2 text-3xl font-bold text-slate-950">{{ $lote->total_candidatos }}</p>
            </div>
            <div class="rounded-lg border border-blue-200 bg-blue-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Aprovadas</p>
                <p class="mt-2 text-3xl font-bold text-blue-900">{{ $lote->total_aprovados }}</p>
            </div>
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Excluídas do AD</p>
                <p class="mt-2 text-3xl font-bold text-emerald-900">{{ $itens->where('status', 'excluido')->count() }}</p>
            </div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Pedem conferência</p>
                <p class="mt-2 text-3xl font-bold text-amber-900">{{ $itens->filter->pedeConferencia()->count() }}</p>
                <p class="mt-1 text-xs text-amber-700">contas de serviço ou já privilegiadas</p>
            </div>
        </div>

        <form method="POST" action="{{ route('ad.exclusao.aprovar', $lote) }}" id="form-aprovar">
            @csrf

            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                @if ($podeDecidir)
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3">
                        <div class="flex items-center gap-3 text-sm">
                            <label class="inline-flex items-center gap-2 font-medium text-slate-700">
                                <input type="checkbox"
                                       id="marcar-todas"
                                       class="rounded border-slate-300"
                                       @click="marcarTodas($event.target.checked)">
                                Marcar todas
                            </label>
                            <span class="text-slate-500">
                                <span x-text="marcados"></span> de {{ $itens->count() }} marcadas para exclusão
                            </span>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" form="form-rejeitar" class="btn-coreti-secondary text-sm">Rejeitar lote</button>
                            <button type="submit"
                                    class="btn-coreti-primary text-sm"
                                    onclick="return confirm('Confirmar a exclusão das contas marcadas? Exclusão no Active Directory não tem desfazer.')">
                                Aprovar marcadas
                            </button>
                        </div>
                    </div>
                @endif

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                @if ($podeDecidir)<th class="w-10 px-4 py-3"></th>@endif
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Conta</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Nome</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Desativada em</th>
                                <th class="px-4 py-3 text-right font-semibold text-slate-600">Dias</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Último logon</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Situação</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($itens as $item)
                                <tr class="{{ $item->pedeConferencia() ? 'bg-amber-50/60' : '' }}">
                                    @if ($podeDecidir)
                                        <td class="px-4 py-3">
                                            {{-- Começam desmarcadas as que pedem conferência: conta de serviço
                                                 costuma ficar desativada de propósito e ainda ser referenciada por
                                                 sistemas; e a marca do AdminSDHolder indica privilégio passado. --}}
                                            <input type="checkbox"
                                                   name="itens[]"
                                                   value="{{ $item->id }}"
                                                   class="item-exclusao rounded border-slate-300"
                                                   @checked(! $item->pedeConferencia())
                                                   @change="recontar()">
                                        </td>
                                    @endif
                                    <td class="px-4 py-3 font-medium text-slate-900">
                                        {{ $item->nome_usuario }}
                                        @if ($item->parece_servico)
                                            <span class="ml-1 inline-flex rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800">
                                                parece serviço
                                            </span>
                                        @endif
                                        @if ($item->ja_privilegiada)
                                            <span class="ml-1 inline-flex rounded bg-purple-100 px-1.5 py-0.5 text-[10px] font-semibold text-purple-800"
                                                  title="adminCount=1: esteve em grupo privilegiado no passado. Não está mais — a marca do AdminSDHolder não é removida na saída do grupo.">
                                                já foi privilegiada
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">{{ $item->nome_exibicao ?? '—' }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $item->desativada_em?->local()->format('d/m/Y') ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right text-slate-700">{{ $item->dias_desativada ?? '—' }}</td>
                                    <td class="px-4 py-3 text-slate-500">{{ $item->ultimo_logon?->local()->format('d/m/Y') ?? 'nunca' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium {{ $item->classesBadge() }}">
                                            {{ $item->rotulo() }}
                                        </span>
                                        @if ($item->resultado_detalhe)
                                            <span class="block text-xs text-slate-500">{{ $item->resultado_detalhe }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </form>

        @if ($podeDecidir)
            <form method="POST" action="{{ route('ad.exclusao.rejeitar', $lote) }}" id="form-rejeitar"
                  onsubmit="return confirm('Rejeitar o lote inteiro? Nenhuma conta será excluída.')">
                @csrf
                <input type="hidden" name="motivo" value="Rejeitado na conferência.">
            </form>
        @endif
    </div>
</x-app-layout>
