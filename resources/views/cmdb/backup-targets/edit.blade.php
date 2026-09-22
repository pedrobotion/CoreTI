<x-app-layout>
    <form method="POST" action="{{ route('cmdb.backup-targets.update', $alvo) }}" class="mx-auto max-w-5xl space-y-6">
        @csrf
        @method('PUT')

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-950">Editar alvo</h1>
                <p class="text-sm text-slate-500">Este cadastro é o que a auditoria automática vai verificar.</p>
            </div>
            <a href="{{ route('cmdb.backup-targets.show', $alvo) }}" class="text-sm text-slate-500 hover:underline">Voltar</a>
        </div>

        @include('admissao.partials.flash')
        @include('cmdb.backup-targets._form', ['alvo' => $alvo, 'metodos' => $metodos, 'ativos' => $ativos])

        <div class="flex justify-end">
            <button type="submit" class="btn-coreti-primary text-sm">Salvar alterações</button>
        </div>
    </form>
</x-app-layout>
