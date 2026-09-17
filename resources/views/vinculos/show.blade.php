<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ $ctx->itemLabel }} #{{ $assignment->id }}</h2>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-6 px-6 py-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-widest text-slate-500">{{ $ctx->label }}</p>
                <h1 class="text-3xl font-semibold text-slate-900">{{ $assignment->collaborator?->name ?? '—' }}</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Matrícula {{ $assignment->collaborator_matricula ?? '—' }}
                    @if ($unidadeColaborador) · {{ $unidadeColaborador }} @endif
                    · {{ $assignment->scope_label }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ $ctx->route('index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Voltar</a>
                @if ($ctx->podeGerenciar())
                    @if ($assignment->ativo)
                        <a href="{{ $ctx->route('edit', $assignment) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Editar</a>
                        <a href="{{ $ctx->route('desvinculo', $assignment) }}" class="rounded-md border border-rose-300 px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50">Registrar devolução</a>
                    @endif
                    <form method="POST" action="{{ $ctx->route('destroy', $assignment) }}"
                          onsubmit="return confirm('Excluir este registro? O histórico será perdido e os itens voltam ao estoque. Para encerrar preservando o histórico, use Registrar devolução.')">
                        @csrf @method('DELETE')
                        <button class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-500 hover:bg-slate-50">Excluir</button>
                    </form>
                @endif
            </div>
        </div>

        @include('partials.modulo-flash')

        @if (! $assignment->ativo)
            <div class="rounded-md border border-slate-300 bg-slate-100 px-4 py-3 text-sm text-slate-700">
                <span class="font-semibold">Encerrado em {{ $assignment->data_desvinculo?->format('d/m/Y H:i') }}</span>
                · Motivo: {{ $assignment->motivo_desvinculo }}
                @if ($assignment->desvinculadoPor) · por {{ $assignment->desvinculadoPor->name }} @endif
                @if ($assignment->observacoes_desvinculo)
                    <p class="mt-1 text-slate-600">{{ $assignment->observacoes_desvinculo }}</p>
                @endif
            </div>
        @elseif ($assignment->isVencido())
            <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                Termo vencido em {{ $assignment->data_vencimento?->format('d/m/Y') }}. Renove a assinatura.
            </div>
        @elseif ($assignment->isProximoVencimento())
            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                Termo vence em {{ $assignment->data_vencimento?->format('d/m/Y') }}.
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Itens entregues</h2>
                <ul class="mt-3 divide-y divide-slate-100">
                    @foreach ($assignment->items as $item)
                        <li class="py-3">
                            <p class="text-sm font-medium text-slate-900">{{ $item->descricao() }}</p>
                            <p class="text-xs text-slate-500">{{ $item->tipoLabel() }}</p>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 text-xs text-slate-400">{{ $assignment->tipo_descricao }}</p>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Dados</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div><dt class="text-slate-500">Entrega</dt><dd class="font-medium text-slate-900">{{ $assignment->data_vinculo?->format('d/m/Y H:i') }}</dd></div>
                    <div><dt class="text-slate-500">Vencimento</dt><dd class="font-medium text-slate-900">{{ $assignment->data_vencimento?->format('d/m/Y') }}</dd></div>
                    <div><dt class="text-slate-500">Registrado por</dt><dd class="font-medium text-slate-900">{{ $assignment->criadoPor?->name ?? '—' }}</dd></div>
                    @if ($assignment->observacoes)
                        <div><dt class="text-slate-500">Observações</dt><dd class="text-slate-700">{{ $assignment->observacoes }}</dd></div>
                    @endif
                </dl>
            </section>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Termo de responsabilidade</h2>
                    @if ($assignment->temTermoAssinado())
                        <p class="mt-1 text-sm text-emerald-700">Via assinada anexada em {{ $assignment->termo_assinado_em?->format('d/m/Y H:i') }}.</p>
                    @else
                        <p class="mt-1 text-sm text-amber-700">Nenhuma via assinada anexada ainda.</p>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ $ctx->route('termo', $assignment) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Gerar termo (PDF)</a>
                    @if ($assignment->temTermoAssinado())
                        <a href="{{ $ctx->route('termo.ver', $assignment) }}" target="_blank" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Ver via assinada</a>
                        @if ($ctx->podeGerenciar())
                            <form method="POST" action="{{ $ctx->route('termo.excluir', $assignment) }}" onsubmit="return confirm('Remover a via assinada?')">
                                @csrf @method('DELETE')
                                <button class="rounded-md border border-rose-300 px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50">Remover via</button>
                            </form>
                        @endif
                    @endif
                </div>
            </div>

            @if ($ctx->podeGerenciar())
                <form method="POST" action="{{ $ctx->route('termo.upload', $assignment) }}" enctype="multipart/form-data" class="mt-4 flex flex-col gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-end">
                    @csrf
                    <div class="flex-1">
                        <label for="termo_assinado" class="mb-1 block text-sm font-medium text-slate-700">Anexar via assinada (PDF, até 50 MB)</label>
                        <input id="termo_assinado" name="termo_assinado" type="file" accept="application/pdf" required class="w-full rounded-md border border-slate-300 p-2 text-sm">
                        <x-input-error :messages="$errors->get('termo_assinado')" class="mt-1" />
                    </div>
                    <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Enviar</button>
                </form>
                <p class="mt-2 text-xs text-slate-500">O arquivo fica em área privada: só sai por este sistema, para quem tem acesso à operação.</p>
            @endif
        </section>
    </div>
</x-app-layout>
