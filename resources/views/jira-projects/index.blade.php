<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Jira</p>
            </div>
            <button type="button" onclick="openJiraCreateModal()" class="toolbar-btn-primary inline-flex items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 font-semibold text-white hover:bg-slate-800">Adicionar Registro</button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Total</p><p class="mt-2 text-2xl font-bold text-slate-950">{{ $stats['total'] }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Ativos</p><p class="mt-2 text-2xl font-bold text-emerald-700">{{ $stats['ativos'] }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Desativados</p><p class="mt-2 text-2xl font-bold text-amber-700">{{ $stats['desativados'] }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Sede</p><p class="mt-2 text-2xl font-bold text-slate-950">{{ $stats['sede'] }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Unidades</p><p class="mt-2 text-2xl font-bold text-slate-950">{{ $stats['unidades'] }}</p></div>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-5">
                <form method="GET" class="toolbar-search-grid">
                    <select name="status" class="toolbar-select text-sm">
                        <option value="todos" @selected($status === 'todos')>Todos</option>
                        <option value="ativo" @selected($status === 'ativo')>Ativos</option>
                        <option value="desativado" @selected($status === 'desativado')>Desativados</option>
                    </select>
                    <select name="tipo_unidade" class="toolbar-select text-sm">
                        <option value="" @selected($tipoUnidade === '')>Tipo</option>
                        <option value="Sede" @selected($tipoUnidade === 'Sede')>Sede</option>
                        <option value="Unidades" @selected($tipoUnidade === 'Unidades')>Unidades</option>
                    </select>
                    <select name="projeto_grupo" class="toolbar-select text-sm">
                        <option value="">Projeto/Grupo</option>
                        @foreach ($grupos as $item)
                            <option value="{{ $item }}" @selected($grupo === $item)>{{ $item }}</option>
                        @endforeach
                    </select>
                    <input name="q" value="{{ $search }}" placeholder="Buscar e-mail, unidade/setor, grupo, centro de custo ou observação" class="toolbar-input min-w-0 rounded-md border-slate-300 text-sm">
                    <button type="submit" class="toolbar-btn-primary inline-flex items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 font-semibold text-white">Buscar</button>
                    <a href="{{ route('jira-projects.index') }}" class="toolbar-btn inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-4 font-semibold text-slate-700">Limpar</a>
                    <button type="submit" name="export" value="csv" class="toolbar-btn inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-4 font-semibold text-slate-700">Exportar CSV</button>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1200px] text-sm">
                    <thead class="bg-slate-900 text-left text-xs font-semibold uppercase text-white">
                        <tr>
                            <th class="px-4 py-3">E-mail</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3">Unidade/Setor</th>
                            <th class="px-4 py-3">Projeto/Grupo</th>
                            <th class="px-4 py-3">Centro Custo</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Obs</th>
                            <th class="px-4 py-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($projects as $project)
                            <tr class="odd:bg-slate-50 even:bg-white">
                                <td class="px-4 py-3">{{ $project->email }}</td>
                                <td class="px-4 py-3">{{ $project->tipo_unidade }}</td>
                                <td class="px-4 py-3">{{ $project->unidade_nome }}</td>
                                <td class="px-4 py-3">{{ $project->projeto_grupo }}</td>
                                <td class="px-4 py-3">{{ $project->centro_custo ?: '-' }}</td>
                                <td class="px-4 py-3">{{ $project->status }}</td>
                                <td class="px-4 py-3">{{ $project->obs ?: '-' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="inline-flex items-center gap-2">
                                        <a href="{{ route('jira-projects.edit', $project) }}" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 text-blue-600 hover:bg-slate-50 hover:text-blue-800" title="Editar" aria-label="Editar">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 20h4l10-10-4-4L4 16v4Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m12 6 4 4" />
                                            </svg>
                                        </a>
                                    <form method="POST" action="{{ route('jira-projects.destroy', $project) }}" class="inline" data-confirm-message="Remover este registro?">
                                        @csrf
                                        @method('DELETE')
                                        <button class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 text-red-600 hover:bg-slate-50 hover:text-red-800" title="Excluir" aria-label="Excluir">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h16" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10 11v6M14 11v6" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 7l1 13h10l1-13M9 7V5h6v2" />
                                            </svg>
                                        </button>
                                    </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-8 text-center text-slate-500">Nenhum registro encontrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-3 border-t border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <form method="GET" class="flex items-center gap-2 text-sm text-slate-600">
                    <span>Itens por página:</span>
                    <input type="hidden" name="q" value="{{ $search }}">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <input type="hidden" name="tipo_unidade" value="{{ $tipoUnidade }}">
                    <input type="hidden" name="projeto_grupo" value="{{ $grupo }}">
                    <select name="per_page" onchange="this.form.submit()" class="toolbar-select text-sm">
                        <option value="10" @selected($perPage === 10)>10</option>
                        <option value="25" @selected($perPage === 25)>25</option>
                        <option value="50" @selected($perPage === 50)>50</option>
                    </select>
                </form>
                {{ $projects->links() }}
            </div>
        </section>
    </div>

    <div id="jira-create-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 px-4">
        <div class="form-modal-shell max-w-6xl rounded-lg border border-slate-200 bg-white p-5 shadow-xl">
            <div class="form-modal-header">
                <h2 class="form-modal-title">Adicionar Registro</h2>
                <button type="button" onclick="closeJiraCreateModal(true)" class="form-modal-close">Fechar</button>
            </div>

            <form method="POST" action="{{ route('jira-projects.store') }}" class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
                x-data="{
                    tipoUnidade: @js(old('tipo_unidade', 'Unidades')),
                    unidades: @js($centroCustoOptionsUnidades),
                    sede: @js($centroCustoOptionsSede),
                    options() {
                        return this.tipoUnidade === 'Sede' ? this.sede : this.unidades;
                    }
                }"
            >
                @csrf

                <div>
                    <label class="form-field-label">E-mail</label>
                    <input type="email" name="email" value="{{ old('email') }}" required class="toolbar-input rounded-md border-slate-300 text-sm">
                </div>

                <div>
                    <label class="form-field-label">Projeto/Grupo</label>
                    <input name="projeto_grupo" list="jira-project-groups-modal" value="{{ old('projeto_grupo') }}" required class="toolbar-input rounded-md border-slate-300 text-sm">
                    <datalist id="jira-project-groups-modal">
                        @foreach ($projetoGrupos as $item)
                            <option value="{{ $item }}"></option>
                        @endforeach
                    </datalist>
                </div>

                <div>
                    <label class="form-field-label">Tipo de unidade</label>
                    <select name="tipo_unidade" x-model="tipoUnidade" class="toolbar-input rounded-md border-slate-300 text-sm">
                        <option value="Unidades">Unidades</option>
                        <option value="Sede">Sede</option>
                    </select>
                </div>

                <div>
                    <label class="form-field-label">Unidade/Setor</label>
                    <input name="unidade_nome" list="jira-unidades-opcoes-modal" value="{{ old('unidade_nome') }}" required class="toolbar-input rounded-md border-slate-300 text-sm">
                    <datalist id="jira-unidades-opcoes-modal">
                        <template x-for="option in options()" :key="`un-${option.name}`">
                            <option :value="option.name"></option>
                        </template>
                    </datalist>
                </div>

                <div>
                    <label class="form-field-label">Centro de custo</label>
                    <input name="centro_custo" list="jira-centro-opcoes-modal" value="{{ old('centro_custo') }}" class="toolbar-input rounded-md border-slate-300 text-sm">
                    <datalist id="jira-centro-opcoes-modal">
                        <template x-for="option in options()" :key="`cc-${option.centro_custo}`">
                            <option :value="option.centro_custo"></option>
                        </template>
                    </datalist>
                </div>

                <div>
                    <label class="form-field-label">Status</label>
                    <select name="status" class="toolbar-input rounded-md border-slate-300 text-sm">
                        <option value="Ativo" @selected(old('status', 'Ativo') === 'Ativo')>Ativo</option>
                        <option value="Desativado" @selected(old('status') === 'Desativado')>Desativado</option>
                    </select>
                </div>

                <div>
                    <label class="form-field-label">Data inclusão</label>
                    <input type="date" name="data_inclusao" value="{{ old('data_inclusao', now()->toDateString()) }}" class="toolbar-input rounded-md border-slate-300 text-sm">
                </div>

                <div>
                    <label class="form-field-label">Data desativação</label>
                    <input type="date" name="data_desativacao" value="{{ old('data_desativacao') }}" class="toolbar-input rounded-md border-slate-300 text-sm">
                </div>

                <div class="sm:col-span-2 lg:col-span-3">
                    <label class="form-field-label">Observação</label>
                    <textarea name="obs" rows="3" class="toolbar-input rounded-md border-slate-300 text-sm">{{ old('obs') }}</textarea>
                </div>

                <div class="sm:col-span-2 lg:col-span-3 flex items-end justify-end gap-2">
                    <button type="button" onclick="closeJiraCreateModal(true)" class="toolbar-btn inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Cancelar</button>
                    <button class="toolbar-btn-primary inline-flex items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Adicionar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openJiraCreateModal() {
            const modal = document.getElementById('jira-create-modal');
            if (!modal) return;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeJiraCreateModal(clear = false) {
            const modal = document.getElementById('jira-create-modal');
            if (!modal) return;
            if (clear) {
                const form = modal.querySelector('form');
                if (form) form.reset();
            }
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }

        @if ($errors->has('email') || $errors->has('unidade_nome') || $errors->has('projeto_grupo'))
            document.addEventListener('DOMContentLoaded', openJiraCreateModal);
        @endif
    </script>
</x-app-layout>
