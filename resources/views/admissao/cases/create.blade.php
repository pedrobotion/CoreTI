<x-app-layout>
    <form method="POST" action="{{ route('admissao.cases.store') }}" class="mx-auto max-w-5xl space-y-6">
        @csrf

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-950">Nova admissão</h1>
                <p class="text-sm text-slate-500">O caso é salvo como rascunho; as tarefas só são disparadas ao enviar.</p>
            </div>
            <a href="{{ route('admissao.cases.index') }}" class="text-sm text-slate-500 hover:underline">Cancelar</a>
        </div>

        @include('admissao.partials.flash')
        @include('admissao.cases._form', ['case' => $case])

        <div class="flex justify-end gap-3">
            <button type="submit" class="btn-coreti-primary text-sm">Salvar rascunho</button>
        </div>
    </form>
</x-app-layout>
