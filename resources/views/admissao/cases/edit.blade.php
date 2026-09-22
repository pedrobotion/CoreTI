<x-app-layout>
    <form method="POST" action="{{ route('admissao.cases.update', $case) }}" class="mx-auto max-w-5xl space-y-6">
        @csrf
        @method('PUT')

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-950">Editar {{ $case->codigo }}</h1>
                <p class="text-sm text-slate-500">Alterar cargo, área ou data de início recalcula as tarefas.</p>
            </div>
            <a href="{{ route('admissao.cases.show', $case) }}" class="text-sm text-slate-500 hover:underline">Voltar</a>
        </div>

        @include('admissao.partials.flash')
        @include('admissao.cases._form', ['case' => $case])

        <div class="flex justify-end gap-3">
            <button type="submit" class="btn-coreti-primary text-sm">Salvar alterações</button>
        </div>
    </form>
</x-app-layout>
