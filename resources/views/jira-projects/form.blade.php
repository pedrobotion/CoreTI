<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Jira</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-950">{{ $mode === 'create' ? 'Adicionar Registro' : 'Editar Registro' }}</h1>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <form method="POST" action="{{ $mode === 'create' ? route('jira-projects.store') : route('jira-projects.update', $record) }}" class="grid gap-3 sm:grid-cols-2">
                @csrf
                @if($mode === 'edit') @method('PUT') @endif

                <div>
                    <label class="text-sm font-medium text-slate-700">E-mail</label>
                    <input type="email" name="email" value="{{ old('email', $record->email) }}" required class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700">Projeto/Grupo</label>
                    <input name="projeto_grupo" list="jira-project-groups" value="{{ old('projeto_grupo', $record->projeto_grupo) }}" required class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                    <datalist id="jira-project-groups">
                        @foreach ($projetoGrupos as $item)
                            <option value="{{ $item }}"></option>
                        @endforeach
                    </datalist>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700">Tipo de unidade</label>
                    <select name="tipo_unidade" id="jira-tipo-unidade" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                        <option value="Unidades" @selected(old('tipo_unidade', $record->tipo_unidade) === 'Unidades')>Unidades</option>
                        <option value="Sede" @selected(old('tipo_unidade', $record->tipo_unidade) === 'Sede')>Sede</option>
                    </select>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700">Unidade/Setor</label>
                    <input name="unidade_nome" list="jira-unidades-opcoes" value="{{ old('unidade_nome', $record->unidade_nome) }}" required class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                    <datalist id="jira-unidades-opcoes">
                        @foreach ($centroCustoOptions as $option)
                            <option value="{{ $option['name'] }}"></option>
                        @endforeach
                    </datalist>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700">Centro de custo</label>
                    <input name="centro_custo" list="jira-centro-opcoes" value="{{ old('centro_custo', $record->centro_custo) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                    <datalist id="jira-centro-opcoes">
                        @foreach ($centroCustoOptions as $option)
                            <option value="{{ $option['centro_custo'] }}"></option>
                        @endforeach
                    </datalist>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700">Status</label>
                    <select name="status" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                        <option value="Ativo" @selected(old('status', $record->status) === 'Ativo')>Ativo</option>
                        <option value="Desativado" @selected(old('status', $record->status) === 'Desativado')>Desativado</option>
                    </select>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700">Data inclusão</label>
                    <input type="date" name="data_inclusao" value="{{ old('data_inclusao', optional($record->data_inclusao)->format('Y-m-d') ?? now()->toDateString()) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700">Data desativação</label>
                    <input type="date" name="data_desativacao" value="{{ old('data_desativacao', optional($record->data_desativacao)->format('Y-m-d')) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                </div>

                <div class="sm:col-span-2">
                    <label class="text-sm font-medium text-slate-700">Observação</label>
                    <textarea name="obs" rows="3" class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">{{ old('obs', $record->obs) }}</textarea>
                </div>

                <div class="sm:col-span-2 flex gap-2 pt-2">
                    <button class="toolbar-btn-primary inline-flex items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 font-semibold text-white hover:bg-slate-800">{{ $mode === 'create' ? 'Adicionar' : 'Salvar' }}</button>
                    <a href="{{ route('jira-projects.index') }}" class="toolbar-btn inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-4 font-semibold text-slate-700">Voltar</a>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>

