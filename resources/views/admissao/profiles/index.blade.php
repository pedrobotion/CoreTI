<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-6">

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-950">Perfis de acesso</h1>
                <p class="text-sm text-slate-500">
                    Define o pacote padrão por cargo, área, departamento ou unidade. Campo em branco vale como "qualquer".
                </p>
            </div>
            <a href="{{ route('admissao.cases.index') }}" class="btn-coreti-secondary text-sm">Voltar aos casos</a>
        </div>

        @include('admissao.partials.flash')

        <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-3">
                <h2 class="text-sm font-semibold text-slate-900">Novo perfil</h2>
            </div>
            <form method="POST" action="{{ route('admissao.profiles.store') }}" class="space-y-4 p-5">
                @csrf
                @include('admissao.profiles._fields', ['profile' => new \App\Models\AdmissionAccessProfile(), 'taskTypes' => $taskTypes])
                <div class="flex justify-end">
                    <button type="submit" class="btn-coreti-primary text-sm">Criar perfil</button>
                </div>
            </form>
        </div>

        @forelse($profiles as $profile)
            <div class="rounded-lg border border-slate-200 bg-white shadow-sm {{ $profile->ativo ? '' : 'opacity-60' }}">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-5 py-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">
                            {{ $profile->nome }}
                            @if($profile->isPadraoGeral())
                                <span class="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">padrão geral</span>
                            @endif
                            @if($profile->cumulativo)
                                <span class="ml-1 rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">cumulativo</span>
                            @endif
                        </h2>
                        <p class="text-xs text-slate-500">
                            Prioridade {{ $profile->prioridade }} · {{ $profile->items->count() }} item(ns)
                            @if($profile->descricao) · {{ $profile->descricao }} @endif
                        </p>
                    </div>
                    <form method="POST" action="{{ route('admissao.profiles.toggle', $profile) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="text-xs text-slate-500 hover:underline">{{ $profile->ativo ? 'Desativar' : 'Ativar' }}</button>
                    </form>
                </div>

                <form method="POST" action="{{ route('admissao.profiles.update', $profile) }}" class="space-y-4 p-5">
                    @csrf @method('PUT')
                    @include('admissao.profiles._fields', ['profile' => $profile, 'taskTypes' => $taskTypes])
                    <div class="flex justify-end">
                        <button type="submit" class="btn-coreti-secondary text-sm">Salvar</button>
                    </div>
                </form>
            </div>
        @empty
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-6 text-sm text-amber-800">
                Nenhum perfil cadastrado. Enquanto não houver ao menos um perfil padrão, os casos não geram tarefas.
            </div>
        @endforelse
    </div>
</x-app-layout>
