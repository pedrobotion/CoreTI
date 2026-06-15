<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Bancada de Serviços</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-950">Configuração de Rotas de Malote</h1>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="text-base font-bold text-slate-900">Nova rota</h2>
            <form method="POST" action="{{ route('bancada-servicos.routes.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @csrf
                <select name="nome" class="rounded-md border-slate-300 text-sm" required>
                    <option value="">Nome da rota</option>
                    @foreach($routeNameOptions as $routeNameOption)
                        <option value="{{ $routeNameOption }}">{{ $routeNameOption }}</option>
                    @endforeach
                </select>
                <select name="dia_separa" class="rounded-md border-slate-300 text-sm" required>
                    <option value="">Dia separação</option>
                    @foreach($weekdayOptions as $weekdayOption)
                        <option value="{{ $weekdayOption }}">{{ $weekdayOption }}</option>
                    @endforeach
                </select>
                <select name="dia_carrega" class="rounded-md border-slate-300 text-sm" required>
                    <option value="">Dia carregamento</option>
                    @foreach($weekdayOptions as $weekdayOption)
                        <option value="{{ $weekdayOption }}">{{ $weekdayOption }}</option>
                    @endforeach
                </select>
                <select name="dia_entrega" class="rounded-md border-slate-300 text-sm" required>
                    <option value="">Dia entrega</option>
                    @foreach($weekdayOptions as $weekdayOption)
                        <option value="{{ $weekdayOption }}">{{ $weekdayOption }}</option>
                    @endforeach
                </select>
                <input name="observacao" placeholder="Observação (opcional)" class="rounded-md border-slate-300 text-sm sm:col-span-2 lg:col-span-3">
                <div
                    class="rounded-md border border-slate-200 p-3 sm:col-span-2 lg:col-span-4"
                    x-data="{
                        q: '',
                        units: @js($unitOptions->map(fn($unit) => mb_strtolower(trim((string) $unit)))->values()),
                        matches(unit) { return unit.includes(this.q.trim().toLowerCase()); },
                        selectVisible() {
                            this.units.forEach((unit, idx) => {
                                if (this.matches(unit)) this.$refs['u_' + idx].checked = true;
                            });
                        },
                        clearSelection() {
                            this.units.forEach((unit, idx) => this.$refs['u_' + idx].checked = false);
                        },
                        selectedCount() {
                            return this.units.reduce((total, unit, idx) => total + (this.$refs['u_' + idx]?.checked ? 1 : 0), 0);
                        }
                    }"
                >
                    <p class="mb-2 text-sm font-medium text-slate-700">Unidades da rota</p>
                    <input x-model="q" type="text" placeholder="Buscar unidade..." class="mb-2 w-full rounded-md border-slate-300 text-sm">
                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <button type="button" @click="selectVisible()" class="rounded-md border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                Selecionar visíveis
                            </button>
                            <button type="button" @click="clearSelection()" class="rounded-md border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                Limpar seleção
                            </button>
                        </div>
                        <p class="text-xs text-slate-600"><span x-text="selectedCount()"></span> unidades selecionadas</p>
                    </div>
                    <div class="max-h-[360px] overflow-y-auto rounded-md border border-slate-300 p-2">
                        <div class="grid gap-1 md:grid-cols-2 xl:grid-cols-3">
                            @foreach($unitOptions as $idx => $unitOption)
                                @php($unitKey = mb_strtolower(trim((string) $unitOption)))
                                <label x-show="matches(@js($unitKey))" class="inline-flex w-full items-center gap-2 rounded px-2 py-1 text-sm text-slate-700 hover:bg-slate-50">
                                    <input x-ref="u_{{ $idx }}" type="checkbox" name="units[]" value="{{ $unitOption }}">
                                    <span>{{ $unitOption }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
                <button class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white">Salvar rota</button>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="text-base font-bold text-slate-900">Rotas cadastradas</h2>
            <div class="mt-4 space-y-4">
                @forelse($routes as $route)
                    <div class="rounded-md border border-slate-200 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-slate-900">
                                {{ $route->nome }}
                                @if($route->ativo)
                                    <span class="ml-2 rounded bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800">Ativa</span>
                                @else
                                    <span class="ml-2 rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-700">Inativa</span>
                                @endif
                            </p>
                            <div class="flex items-center gap-2">
                                <form method="POST" action="{{ route('bancada-servicos.routes.toggle', $route) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button class="rounded-md border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                        {{ $route->ativo ? 'Desativar' : 'Ativar' }}
                                    </button>
                                </form>
                                <button type="button" onclick="document.getElementById('route-edit-{{ $route->id }}').classList.toggle('hidden')" class="rounded-md border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">Editar</button>
                            </div>
                        </div>
                        <p class="text-xs text-slate-600">Separa: {{ $route->dia_separa ?: '-' }} | Carrega: {{ $route->dia_carrega ?: '-' }} | Entrega: {{ $route->dia_entrega ?: '-' }}</p>
                        @if($route->observacao)
                            <p class="mt-1 text-xs text-slate-600">Obs: {{ $route->observacao }}</p>
                        @endif
                        <div class="mt-2 text-sm text-slate-700">{{ $route->units->pluck('unit_label')->implode(' | ') }}</div>

                        <div id="route-edit-{{ $route->id }}" class="mt-4 hidden rounded-md border border-slate-200 bg-slate-50 p-3">
                            <form method="POST" action="{{ route('bancada-servicos.routes.update', $route) }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                @csrf
                                @method('PUT')
                                <select name="nome" class="rounded-md border-slate-300 text-sm" required>
                                    @foreach($routeNameOptions as $routeNameOption)
                                        <option value="{{ $routeNameOption }}" @selected($route->nome === $routeNameOption)>{{ $routeNameOption }}</option>
                                    @endforeach
                                </select>
                                <select name="dia_separa" class="rounded-md border-slate-300 text-sm" required>
                                    @foreach($weekdayOptions as $weekdayOption)
                                        <option value="{{ $weekdayOption }}" @selected($route->dia_separa === $weekdayOption)>{{ $weekdayOption }}</option>
                                    @endforeach
                                </select>
                                <select name="dia_carrega" class="rounded-md border-slate-300 text-sm" required>
                                    @foreach($weekdayOptions as $weekdayOption)
                                        <option value="{{ $weekdayOption }}" @selected($route->dia_carrega === $weekdayOption)>{{ $weekdayOption }}</option>
                                    @endforeach
                                </select>
                                <select name="dia_entrega" class="rounded-md border-slate-300 text-sm" required>
                                    @foreach($weekdayOptions as $weekdayOption)
                                        <option value="{{ $weekdayOption }}" @selected($route->dia_entrega === $weekdayOption)>{{ $weekdayOption }}</option>
                                    @endforeach
                                </select>
                                <input name="observacao" value="{{ $route->observacao }}" placeholder="Observação (opcional)" class="rounded-md border-slate-300 text-sm sm:col-span-2 lg:col-span-3">
                                <div
                                    class="rounded-md border border-slate-200 bg-white p-2 sm:col-span-2 lg:col-span-4"
                                    x-data="{
                                        q: '',
                                        units: @js($unitOptions->map(fn($unit) => mb_strtolower(trim((string) $unit)))->values()),
                                        matches(unit) { return unit.includes(this.q.trim().toLowerCase()); },
                                        selectVisible() {
                                            this.units.forEach((unit, idx) => {
                                                if (this.matches(unit)) this.$refs['eu_' + idx].checked = true;
                                            });
                                        },
                                        clearSelection() {
                                            this.units.forEach((unit, idx) => this.$refs['eu_' + idx].checked = false);
                                        },
                                        selectedCount() {
                                            return this.units.reduce((total, unit, idx) => total + (this.$refs['eu_' + idx]?.checked ? 1 : 0), 0);
                                        }
                                    }"
                                >
                                    <p class="mb-2 text-xs font-semibold text-slate-700">Unidades da rota</p>
                                    <input x-model="q" type="text" placeholder="Buscar unidade..." class="mb-2 w-full rounded-md border-slate-300 text-sm">
                                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            <button type="button" @click="selectVisible()" class="rounded-md border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                                Selecionar visíveis
                                            </button>
                                            <button type="button" @click="clearSelection()" class="rounded-md border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                                Limpar seleção
                                            </button>
                                        </div>
                                        <p class="text-xs text-slate-600"><span x-text="selectedCount()"></span> unidades selecionadas</p>
                                    </div>
                                    <div class="max-h-[360px] overflow-y-auto rounded-md border border-slate-300 p-2">
                                        <div class="grid gap-1 md:grid-cols-2 xl:grid-cols-3">
                                        @php($selectedUnits = $route->units->pluck('unit_label')->map(fn($u) => mb_strtolower(trim($u)))->values()->all())
                                        @foreach($unitOptions as $idx => $unitOption)
                                            @php($unitKey = mb_strtolower(trim($unitOption)))
                                            <label x-show="matches(@js($unitKey))" class="inline-flex w-full items-center gap-2 rounded px-2 py-1 text-sm text-slate-700 hover:bg-slate-50">
                                                <input x-ref="eu_{{ $idx }}" type="checkbox" name="units[]" value="{{ $unitOption }}" @checked(in_array($unitKey, $selectedUnits, true))>
                                                <span>{{ $unitOption }}</span>
                                            </label>
                                        @endforeach
                                        </div>
                                    </div>
                                </div>
                                <button class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white">Salvar alterações</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Nenhuma rota cadastrada.</p>
                @endforelse
            </div>
        </section>

    </div>
</x-app-layout>
