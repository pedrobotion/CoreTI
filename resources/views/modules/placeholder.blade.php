{{--
    Tela de espera das rotas de Telefonia e Equipamentos.

    Existe para que os itens de menu da Fase 1 levem a algum lugar honesto
    enquanto as telas reais não chegam, em vez de apontarem para rota
    inexistente. Cada rota é substituída pela tela de verdade na sua fase.
--}}
<x-app-layout>
    <div class="mx-auto max-w-5xl">
        <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $section }}</p>
            <h1 class="mt-2 text-2xl font-bold text-slate-950 dark:text-white">{{ $title }}</h1>
            <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">{{ $description }}</p>

            <div class="mt-5 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-900/50 dark:bg-amber-950/40">
                <p class="text-sm text-amber-900 dark:text-amber-200">
                    Estrutura criada. A tela desta seção entra na próxima fase da implantação.
                </p>
            </div>

            @php
                $escoposVisiveis = collect(auth()->user()?->visibleScopes() ?? [])
                    ->map(fn (string $scope) => \App\Support\ScopeRegistry::label($scope));
            @endphp

            <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">
                Seus escopos:
                <span class="font-semibold text-slate-700 dark:text-slate-200">
                    {{ $escoposVisiveis->isEmpty() ? 'nenhum concedido' : $escoposVisiveis->join(', ') }}
                </span>
            </p>
        </div>
    </div>
</x-app-layout>
