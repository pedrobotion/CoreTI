<x-app-layout>
    <form method="POST" action="{{ route('cmdb.backup-targets.store') }}" class="mx-auto max-w-5xl space-y-6">
        @csrf
        

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-950">Novo alvo de backup</h1>
                <p class="text-sm text-slate-500">Este cadastro é o que a auditoria automática vai verificar.</p>
            </div>
            <a href="{{ route('cmdb.backup-targets.index') }}" class="text-sm text-slate-500 hover:underline">Voltar</a>
        </div>

        @include('admissao.partials.flash')
        @include('cmdb.backup-targets._form', ['alvo' => $alvo, 'metodos' => $metodos, 'ativos' => $ativos])

        <div class="flex justify-end">
            <button type="submit" class="btn-coreti-primary text-sm">Cadastrar alvo</button>
        </div>
    </form>
</x-app-layout>
