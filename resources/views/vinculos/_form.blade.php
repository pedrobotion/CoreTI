@php
    $selecionadoInicial = $colaborador ? [
        'id' => $colaborador->id,
        'nome' => $colaborador->name,
        'matricula' => $colaborador->employee_number,
    ] : null;
@endphp

<div
    x-data="{
        termo: '',
        resultados: [],
        buscando: false,
        selecionado: @js($selecionadoInicial),
        async buscar() {
            if (this.termo.trim().length < 2) { this.resultados = []; return }
            this.buscando = true
            try {
                const r = await fetch(@js($ctx->route('lookup.colaboradores')) + '?termo=' + encodeURIComponent(this.termo))
                this.resultados = r.ok ? await r.json() : []
            } catch (e) { this.resultados = [] }
            this.buscando = false
        },
        escolher(c) { this.selecionado = c; this.resultados = []; this.termo = '' },
        limpar() { this.selecionado = null; this.resultados = []; this.termo = '' },
    }"
    class="space-y-6"
>
    <section>
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Colaborador</h3>

        <div class="mt-3" x-show="!selecionado">
            <label for="busca-colaborador" class="mb-1 block text-sm font-medium text-slate-700">Buscar por nome ou matrícula <span class="text-rose-600">*</span></label>
            <input
                id="busca-colaborador"
                type="text"
                x-model="termo"
                @input.debounce.350ms="buscar()"
                placeholder="Digite ao menos 2 caracteres"
                autocomplete="off"
                class="w-full rounded-md border-slate-300 text-sm"
            >
            <p class="mt-1 text-xs text-slate-500">Somente colaboradores ativos no RH aparecem na busca.</p>

            <div x-show="buscando" x-cloak class="mt-2 text-xs text-slate-500">Buscando…</div>

            <ul x-show="resultados.length" x-cloak class="mt-2 max-h-72 divide-y divide-slate-100 overflow-y-auto rounded-md border border-slate-200">
                <template x-for="c in resultados" :key="c.id">
                    <li>
                        <button type="button" @click="escolher(c)" class="block w-full px-3 py-2 text-left hover:bg-slate-50">
                            <span class="text-sm font-medium text-slate-900" x-text="c.nome"></span>
                            <span class="block text-xs text-slate-500">
                                Matrícula <span x-text="c.matricula"></span>
                                <template x-if="c.unidade"><span> · <span x-text="c.unidade"></span></span></template>
                            </span>
                        </button>
                    </li>
                </template>
            </ul>

            <div x-show="termo.length >= 2 && !buscando && resultados.length === 0" x-cloak class="mt-2 rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-500">
                Nenhum colaborador ativo encontrado.
            </div>
        </div>

        <div x-show="selecionado" x-cloak class="mt-3 flex items-center justify-between gap-3 rounded-md border border-slate-200 bg-slate-50 px-4 py-3">
            <div>
                <p class="text-sm font-semibold text-slate-900" x-text="selecionado?.nome"></p>
                <p class="text-xs text-slate-500">
                    Matrícula <span x-text="selecionado?.matricula"></span>
                    <template x-if="selecionado?.unidade"><span> · <span x-text="selecionado?.unidade"></span></span></template>
                </p>
            </div>
            <button type="button" @click="limpar()" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Trocar</button>
        </div>

        <input type="hidden" name="hr_collaborator_id" :value="selecionado?.id ?? ''">
        <x-input-error :messages="$errors->get('hr_collaborator_id')" class="mt-1" />
    </section>

    <section class="border-t border-slate-100 pt-5">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Itens entregues</h3>
        <p class="mt-1 text-xs text-slate-500">
            @if ($ctx->key === \App\Support\AssignmentContext::TELEFONIA)
                Só a linha = chip avulso. Linha + aparelho = kit completo. Um termo só cobre os dois.
            @else
                Apenas equipamentos que são entregues a pessoa: {{ implode(', ', \App\Models\Equipment::TIPOS_VINCULAVEIS) }}.
            @endif
        </p>

        <x-input-error :messages="$errors->get('itens')" class="mt-2" />

        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
            @foreach ($ctx->kinds as $kind)
                @php
                    $lista = $itensDisponiveis[$kind] ?? collect();
                    $atual = $itensSelecionados[$kind] ?? null;
                    $obrigatorio = $kind === 'linha' || $kind === 'equipamento';
                @endphp
                <div>
                    <label for="item-{{ $kind }}" class="mb-1 block text-sm font-medium text-slate-700">
                        {{ \App\Support\AssignmentContext::ROTULOS[$kind] }}
                        @if ($obrigatorio)<span class="text-rose-600">*</span>@endif
                    </label>
                    <select id="item-{{ $kind }}" name="itens[{{ $kind }}]" class="w-full rounded-md border-slate-300 text-sm">
                        <option value="">{{ $obrigatorio ? 'Selecione' : 'Nenhum' }}</option>
                        @foreach ($lista as $opcao)
                            <option value="{{ $opcao->id }}" @selected((string) old("itens.{$kind}", $atual?->id) === (string) $opcao->id)>
                                @if ($kind === 'linha')
                                    {{ $opcao->numero_formatado }} — {{ $opcao->operadora }}{{ $opcao->nome_plano ? ' · '.$opcao->nome_plano : '' }}
                                @elseif ($kind === 'aparelho')
                                    {{ $opcao->descricao }} — {{ $opcao->imei_1_formatado ? 'IMEI '.$opcao->imei_1_formatado : ($opcao->numero_serie ? 'Série '.$opcao->numero_serie : 'sem IMEI') }}
                                @else
                                    {{ $opcao->patrimonio }} — {{ $opcao->nome }} ({{ $opcao->tipo }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">{{ $lista->count() }} disponível(is) na sua operação.</p>
                    <x-input-error :messages="$errors->get('itens.'.$kind)" class="mt-1" />
                </div>
            @endforeach
        </div>
    </section>

    <section class="border-t border-slate-100 pt-5">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Dados da entrega</h3>

        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="data_vinculo" class="mb-1 block text-sm font-medium text-slate-700">Data da entrega <span class="text-rose-600">*</span></label>
                <input id="data_vinculo" name="data_vinculo" type="date" required
                       value="{{ old('data_vinculo', $assignment->data_vinculo?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                       class="w-full rounded-md border-slate-300 text-sm">
                <p class="mt-1 text-xs text-slate-500">O termo vence {{ \App\Models\AssetAssignment::VIGENCIA_ANOS }} anos depois desta data.</p>
                <x-input-error :messages="$errors->get('data_vinculo')" class="mt-1" />
            </div>
        </div>

        <div class="mt-4">
            <label for="observacoes" class="mb-1 block text-sm font-medium text-slate-700">Observações</label>
            <textarea id="observacoes" name="observacoes" rows="3" maxlength="1000" class="w-full rounded-md border-slate-300 text-sm">{{ old('observacoes', $assignment->observacoes) }}</textarea>
            <x-input-error :messages="$errors->get('observacoes')" class="mt-1" />
        </div>
    </section>
</div>
