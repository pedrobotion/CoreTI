<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-950">Cadastro incompleto no AD</h1>
                <p class="text-sm text-slate-500">
                    Colaboradores ativos e presentes no domínio com algum campo em branco.
                    Ficam de fora: conta desativada ou já excluída (histórico, não pendência),
                    conta que não é de pessoa (scanner, serviço, integração — não têm matrícula nem cargo)
                    e as estruturas de Concessionárias e Terceiros, que seguem outro modelo de cadastro.
                </p>
            </div>
            <a href="{{ route('service-desk.ad.cadastro-incompleto.exportar', request()->query()) }}" class="btn-coreti-secondary text-sm">
                Exportar CSV
            </a>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ($campos as $chave => $rotulo)
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sem {{ mb_strtolower($rotulo) }}</p>
                    <p class="mt-2 text-3xl font-bold {{ $contagens[$chave] > 0 ? 'text-amber-700' : 'text-slate-950' }}">
                        {{ $contagens[$chave] }}
                    </p>
                </div>
            @endforeach
            <div class="rounded-lg border border-slate-300 bg-slate-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">Contas afetadas</p>
                <p class="mt-2 text-3xl font-bold text-slate-950">{{ $contagens['qualquer'] }}</p>
                <p class="mt-1 text-xs text-slate-500">com pelo menos um campo vazio</p>
            </div>
        </div>

        <form method="GET" class="space-y-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-center gap-4">
                <span class="text-xs font-semibold text-slate-600">Mostrar quem está sem:</span>
                @foreach ($campos as $chave => $rotulo)
                    <label class="inline-flex items-center gap-1.5 text-sm text-slate-700">
                        <input type="checkbox" name="campos[]" value="{{ $chave }}"
                               class="rounded border-slate-300" @checked(in_array($chave, $selecionados, true))>
                        {{ $rotulo }}
                    </label>
                @endforeach
                <span class="text-xs text-slate-400">nenhum marcado = qualquer campo</span>
            </div>

            <div class="flex flex-wrap items-end gap-3 border-t border-slate-100 pt-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-600">Conta ou nome</label>
                    <input type="text" name="q" value="{{ $busca }}" class="mt-1 w-64 rounded-md border-slate-300 text-sm">
                </div>
                <label class="inline-flex items-center gap-1.5 text-sm text-slate-700">
                    <input type="checkbox" name="servico" value="1" class="rounded border-slate-300" @checked($incluirForaDeEscopo)>
                    incluir o que está fora de escopo
                </label>
                <button type="submit" class="btn-coreti-secondary text-sm">Filtrar</button>
                <a href="{{ route('service-desk.ad.cadastro-incompleto.index') }}" class="text-xs text-slate-500 hover:text-slate-700">limpar</a>
            </div>
        </form>

        <details class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <summary class="cursor-pointer px-5 py-3 text-sm font-semibold text-slate-800">
                {{ $naoColaboradores->count() }} contas marcadas como "não é colaborador"
            </summary>
            <div class="border-t border-slate-200">
                <p class="px-5 py-3 text-xs text-slate-500">
                    Balança, barraca, impressora, guarita, terminal, conta de serviço e conta de máquina.
                    Saem deste relatório e também da regra de centro de custo.
                </p>
                <ul class="divide-y divide-slate-100">
                    @foreach ($naoColaboradores as $nc)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-2 text-sm">
                            <div>
                                <span class="font-medium text-slate-900">{{ $nc->nome_usuario }}</span>
                                <span class="ml-2 text-slate-500">{{ $nc->motivo }}</span>
                            </div>
                            @can('module.servicedesk.manage')
                                <form method="POST" action="{{ route('service-desk.ad.cadastro-incompleto.nao-colaborador.destroy', $nc) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-slate-500 underline hover:text-slate-800">devolver ao relatório</button>
                                </form>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            </div>
        </details>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-5 py-3 text-left font-semibold text-slate-600">Conta</th>
                            <th class="px-5 py-3 text-left font-semibold text-slate-600">Nome</th>
                            <th class="px-5 py-3 text-left font-semibold text-slate-600">Unidade / setor</th>
                            <th class="px-5 py-3 text-left font-semibold text-slate-600">Cargo</th>
                            <th class="px-5 py-3 text-left font-semibold text-slate-600">Matrícula</th>
                            <th class="px-5 py-3 text-left font-semibold text-slate-600">Centro de custo</th>
                            <th class="px-5 py-3 text-left font-semibold text-slate-600">Falta</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($contas as $conta)
                            @php($faltando = $servico->faltando($conta))
                            <tr>
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $conta->nome_usuario }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $conta->nome_completo ?: '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">
                                    {{ $conta->unidade_principal ?: '—' }}
                                    <span class="block text-xs text-slate-400">{{ $conta->unidade_setor ?: '' }}</span>
                                </td>
                                <td class="px-5 py-3 {{ $conta->Cargo ? 'text-slate-600' : 'text-amber-700' }}">{{ $conta->Cargo ?: '—' }}</td>
                                <td class="px-5 py-3 {{ $conta->matricula ? 'text-slate-600' : 'text-amber-700' }}">{{ $conta->matricula ?: '—' }}</td>
                                <td class="px-5 py-3 {{ $conta->centro_custo ? 'text-slate-600' : 'text-amber-700' }}">{{ $conta->centro_custo ?: '—' }}</td>
                                <td class="px-5 py-3">
                                    @foreach ($faltando as $f)
                                        <span class="mr-1 inline-flex rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800">{{ $f }}</span>
                                    @endforeach
                                </td>
                                <td class="px-5 py-3 text-right">
                                    @can('module.servicedesk.manage')
                                        <form method="POST" action="{{ route('service-desk.ad.cadastro-incompleto.nao-colaborador.store') }}"
                                              onsubmit="return confirm('Marcar {{ $conta->nome_usuario }} como conta que não é de colaborador? Ela sai deste relatório e da regra de centro de custo.')">
                                            @csrf
                                            <input type="hidden" name="nome_usuario" value="{{ $conta->nome_usuario }}">
                                            <button type="submit" class="text-xs text-slate-500 underline hover:text-slate-800">não é colaborador</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-5 py-10 text-center text-slate-500">
                                    Nenhuma conta com esses campos em branco.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{ $contas->links() }}
    </div>
</x-app-layout>
