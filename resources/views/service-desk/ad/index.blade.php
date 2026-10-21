<x-app-layout>
    <div class="mx-auto max-w-[1500px] space-y-6 px-4 py-8 sm:px-6" x-data="{
        mode: 'regions',
        selectedDivision: @js(in_array($category, ['sede', 'pr', 'go', 'outras'], true) ? $category : 'sede'),
        divisions: @js($breakdown),
        industries: @js($industryBreakdown),
        groups: @js($groupsBreakdown),
        maxIndustry: {{ max(1, (int) collect($industryBreakdown)->max('count')) }},
        maxGroup: {{ max(1, (int) collect($groupsBreakdown)->max('count')) }}
    }">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between" style="background:linear-gradient(115deg,#0f172a 0%,#123b5d 100%);padding:28px 30px;">
                <div class="text-white">
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-cyan-300">ServiceDesk / Inteligência organizacional</p>
                    <h1 class="mt-2 text-3xl font-bold tracking-tight">Painel Active Directory</h1>
                    <p class="mt-2 max-w-2xl text-sm text-slate-300">Visão executiva da base de usuários, distribuição organizacional e pontos de atenção cadastral.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('service-desk.ad.export', request()->query()) }}" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-emerald-500 px-4 text-sm font-bold text-white shadow-sm hover:bg-emerald-400">Exportar Excel</a>
                    <a href="{{ route('service-desk.dashboard') }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-500 px-4 text-sm font-semibold text-white hover:bg-white/10">Voltar</a>
                </div>
            </div>
            <div class="grid divide-y divide-slate-200 sm:grid-cols-2 sm:divide-x sm:divide-y-0 lg:grid-cols-4 dark:divide-slate-800" style="padding:0 8px;">
                <div style="padding:20px 22px;"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Usuários no painel</p><p class="mt-2 text-3xl font-bold text-slate-950 dark:text-white">{{ number_format($summary['total'], 0, ',', '.') }}</p></div>
                <div style="padding:20px 22px;"><p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Ativos</p><p class="mt-2 text-3xl font-bold text-emerald-700">{{ number_format($summary['ativos'], 0, ',', '.') }}</p><p class="mt-1 text-xs text-slate-500">{{ $summary['total'] > 0 ? number_format($summary['ativos'] / $summary['total'] * 100, 1, ',', '.') : '0,0' }}% da base</p></div>
                <div style="padding:20px 22px;"><p class="text-xs font-semibold uppercase tracking-wide text-amber-600">Pontos de atenção</p><p class="mt-2 text-3xl font-bold text-amber-700">{{ number_format($dataQuality['sem_matricula'] + $dataQuality['sem_unidade'] + $dataQuality['sem_cargo'], 0, ',', '.') }}</p><p class="mt-1 text-xs text-slate-500">cadastros incompletos de colaborador</p></div>
                <div style="padding:20px 22px;"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Última sincronização</p><p class="mt-2 text-lg font-bold text-slate-950 dark:text-white">{{ $latestSync?->format('d/m/Y H:i') ?: 'Não informado' }}</p><p class="mt-1 text-xs text-slate-500">Fonte: Active Directory</p></div>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([['key' => 'sede', 'label' => 'Sede', 'value' => $summary['sede'], 'color' => '#2563eb'], ['key' => 'pr', 'label' => 'Unidades PR', 'value' => $summary['pr'], 'color' => '#7c3aed'], ['key' => 'go', 'label' => 'Unidades GO', 'value' => $summary['go'], 'color' => '#d97706'], ['key' => 'outras', 'label' => 'Outras / sem unidade', 'value' => $summary['outras'] + $summary['sem_unidade'], 'color' => '#e11d48']] as $division)
                <button type="button" @click="selectedDivision = '{{ $division['key'] }}'; mode = 'regions'" class="group rounded-xl border bg-white p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-800 dark:bg-slate-900" :class="selectedDivision === '{{ $division['key'] }}' ? 'ring-2 ring-slate-900 dark:ring-white' : 'border-slate-200'">
                    <div class="flex items-start justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ $division['label'] }}</p><p class="mt-2 text-3xl font-bold text-slate-950 dark:text-white">{{ number_format($division['value'], 0, ',', '.') }}</p></div><span class="h-3 w-3 rounded-full" style="background:{{ $division['color'] }}"></span></div>
                    <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-full rounded-full" style="width:{{ $summary['total'] > 0 ? min(100, round($division['value'] / $summary['total'] * 100)) : 0 }}%;background:{{ $division['color'] }}"></div></div>
                    <p class="mt-2 text-xs text-slate-500">{{ $summary['total'] > 0 ? number_format($division['value'] / $summary['total'] * 100, 1, ',', '.') : '0,0' }}% da base</p>
                </button>
            @endforeach
        </section>

        <section class="grid gap-5 xl:grid-cols-[1.35fr_1fr]">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="text-lg font-bold text-slate-950 dark:text-white">Distribuição da base</h2><p class="mt-1 text-sm text-slate-500">Selecione uma divisão para explorar seus departamentos e unidades.</p></div><div class="flex rounded-lg bg-slate-100 p-1 text-xs font-semibold dark:bg-slate-800"><button @click="mode='regions'" :class="mode === 'regions' ? 'bg-white shadow-sm dark:bg-slate-700' : ''" class="rounded-md px-3 py-2">Regiões</button><button @click="mode='industries'" :class="mode === 'industries' ? 'bg-white shadow-sm dark:bg-slate-700' : ''" class="rounded-md px-3 py-2">Indústrias</button></div></div>
                <div x-show="mode === 'regions'" x-cloak class="mt-5 max-h-96 space-y-3 overflow-y-auto pr-2">
                    <template x-for="row in (divisions[selectedDivision] || [])" :key="row.label">
                        <a :href="'{{ route('service-desk.ad') }}?category=' + selectedDivision + '&q=' + encodeURIComponent(row.label)" class="block rounded-lg p-2 transition hover:bg-slate-50 dark:hover:bg-slate-800/70"><div class="mb-1 flex items-center justify-between gap-3 text-sm"><span class="truncate font-medium text-slate-700 dark:text-slate-200" x-text="row.label"></span><strong class="text-slate-950 dark:text-white" x-text="row.count.toLocaleString('pt-BR')"></strong></div><div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-full rounded-full bg-sky-600 transition-all" :style="'width:' + row.percent + '%' "></div></div></a>
                    </template>
                    <p x-show="(divisions[selectedDivision] || []).length === 0" class="py-10 text-center text-sm text-slate-500">Nenhum registro encontrado.</p>
                </div>
                <div x-show="mode === 'industries'" x-cloak class="mt-5 max-h-96 space-y-3 overflow-y-auto pr-2">
                    <template x-for="row in industries" :key="row.label"><div class="rounded-lg p-2"><div class="mb-1 flex items-center justify-between gap-3 text-sm"><span class="font-medium text-slate-700 dark:text-slate-200" x-text="row.label"></span><strong class="text-slate-950 dark:text-white" x-text="row.count.toLocaleString('pt-BR')"></strong></div><div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-full rounded-full bg-cyan-500" :style="'width:' + Math.min(100, row.count / maxIndustry * 100) + '%' "></div></div></div></template>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-start justify-between gap-3"><div><h2 class="text-lg font-bold text-slate-950 dark:text-white">Qualidade cadastral</h2><p class="mt-1 text-sm text-slate-500">Itens que merecem revisão operacional.</p>@if(($dataQuality['fora_da_contagem'] ?? 0) > 0)<p class="mt-1 text-xs text-slate-400">Não inclui {{ number_format($dataQuality['fora_da_contagem'], 0, ',', '.') }} contas fora do cadastro de colaborador. <a href="{{ route('service-desk.ad.cadastro-incompleto.index') }}" class="underline">Ver detalhe</a></p>@endif</div><span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-700">{{ number_format($dataQuality['sem_matricula'] + $dataQuality['sem_unidade'] + $dataQuality['sem_cargo'], 0, ',', '.') }} pontos</span></div>
                <div class="mt-6 space-y-4">
                    @foreach ([['label' => 'Sem matrícula', 'value' => $dataQuality['sem_matricula'], 'color' => '#f59e0b'], ['label' => 'Sem unidade', 'value' => $dataQuality['sem_unidade'], 'color' => '#ef4444'], ['label' => 'Sem cargo', 'value' => $dataQuality['sem_cargo'], 'color' => '#8b5cf6'], ['label' => 'Sem centro de custo', 'value' => $dataQuality['sem_centro_custo'], 'color' => '#06b6d4']] as $quality)
                        <div><div class="mb-1 flex justify-between text-sm"><span class="text-slate-600 dark:text-slate-300">{{ $quality['label'] }}</span><strong class="text-slate-950 dark:text-white">{{ number_format($quality['value'], 0, ',', '.') }}</strong></div><div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-full rounded-full" style="width:{{ $summary['total'] > 0 ? min(100, round($quality['value'] / $summary['total'] * 100)) : 0 }}%;background:{{ $quality['color'] }}"></div></div></div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="text-lg font-bold text-slate-950 dark:text-white">Grupos de acesso</h2><p class="mt-1 text-sm text-slate-500">Principais grupos associados aos usuários desta visão.</p></div><span class="text-xs text-slate-500">{{ number_format($users->sum(fn ($user) => count($user->ad_groups_list ?? [])), 0, ',', '.') }} associações</span></div>
            <div class="mt-5 grid max-h-64 gap-3 overflow-y-auto pr-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"><template x-for="row in groups" :key="row.label"><div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"><div class="flex items-center justify-between gap-2 text-sm"><span class="truncate text-slate-600 dark:text-slate-300" :title="row.label" x-text="row.label"></span><strong class="shrink-0 text-slate-950 dark:text-white" x-text="row.count.toLocaleString('pt-BR')"></strong></div><div class="mt-2 h-1.5 rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-full rounded-full bg-slate-700" :style="'width:' + Math.min(100, row.count / maxGroup * 100) + '%' "></div></div></div></template></div>
        </section>

        <details class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <summary class="cursor-pointer list-none px-6 py-5 text-base font-bold text-slate-950 dark:text-white">Consulta detalhada de usuários <span class="ml-2 text-sm font-normal text-slate-500">{{ number_format($users->total(), 0, ',', '.') }} registros</span></summary>
            <div class="border-t border-slate-200 p-6 dark:border-slate-800">
                <form method="GET" class="grid gap-3 lg:grid-cols-12 lg:items-end">
                    <label class="block text-sm font-medium text-slate-700 lg:col-span-4 dark:text-slate-200">Pesquisar<input name="q" value="{{ $search }}" placeholder="Nome, e-mail, login ou unidade" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm"></label>
                    <label class="block text-sm font-medium text-slate-700 lg:col-span-2 dark:text-slate-200">Região<select name="category" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm"><option value="">Todas</option>@foreach ($categories as $key => $label)<option value="{{ $key }}" @selected($category === $key)>{{ $label }}</option>@endforeach</select></label>
                    <label class="block text-sm font-medium text-slate-700 lg:col-span-2 dark:text-slate-200">Indústria<select name="industry" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm"><option value="">Todas</option>@foreach ($industries as $key => $label)<option value="{{ $key }}" @selected($industry === $key)>{{ $label }}</option>@endforeach</select></label>
                    <label class="block text-sm font-medium text-slate-700 lg:col-span-2 dark:text-slate-200">Status<select name="status" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm"><option value="active" @selected($status === 'active')>Ativos</option><option value="inactive" @selected($status === 'inactive')>Inativos</option><option value="all" @selected($status === 'all')>Todos</option></select></label>
                    <div class="flex gap-2 lg:col-span-2"><button style="background-color: #033151; border-color: #033151;" class="h-10 flex-1 rounded-lg px-4 text-sm font-semibold text-white hover:opacity-90">Buscar</button><a href="{{ route('service-desk.ad') }}" class="inline-flex h-10 items-center rounded-md border border-slate-300 px-3 text-sm font-semibold text-slate-700">Limpar</a></div>
                </form>
                <div class="mt-5 overflow-x-auto"><table class="w-full min-w-[1450px] text-sm"><thead class="border-b border-slate-100 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-400"><tr><th class="px-5 py-3">Nome</th><th class="px-5 py-3">E-mail</th><th class="px-5 py-3">Matrícula</th><th class="px-5 py-3">Unidade / setor</th><th class="px-5 py-3">Centro de custo</th><th class="px-5 py-3">Cargo</th><th class="px-5 py-3">Região</th><th class="px-5 py-3">Indústria</th><th class="px-5 py-3">Grupos AD</th><th class="px-5 py-3">Status</th></tr></thead><tbody class="divide-y divide-slate-100 dark:divide-slate-800">@forelse ($users as $user)<tr class="align-top"><td class="px-5 py-4 font-semibold">{{ $user->nome_completo ?: $user->nome_exibicao ?: '-' }}<div class="mt-1 text-xs font-normal text-slate-400">{{ $user->nome_usuario ?: '-' }}</div></td><td class="px-5 py-4">{{ $user->email ?: '-' }}</td><td class="px-5 py-4">{{ $user->matricula ?: '-' }}</td><td class="px-5 py-4">{{ $user->unidade_setor ?: 'Não definida' }}</td><td class="px-5 py-4">{{ $user->centro_custo ?: '-' }}@if($user->canonical_cost_center_name)<div class="mt-1 text-xs text-slate-500">{{ $user->canonical_cost_center_name }}</div>@endif</td><td class="px-5 py-4">{{ $user->Cargo ?: '-' }}</td><td class="px-5 py-4">{{ $user->ad_category_label }}</td><td class="px-5 py-4">{{ $user->ad_industry_label ?: '-' }}</td><td class="px-5 py-4 text-xs">{{ $user->ad_groups_preview }}</td><td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $user->e_ativo ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $user->e_ativo ? 'Ativo' : 'Inativo' }}</span></td></tr>@empty<tr><td colspan="10" class="px-5 py-10 text-center text-slate-500">Nenhum usuário encontrado.</td></tr>@endforelse</tbody></table></div>
                @if ($users->hasPages())<div class="mt-4">{{ $users->onEachSide(1)->links() }}</div>@endif
            </div>
        </details>
    </div>
</x-app-layout>
