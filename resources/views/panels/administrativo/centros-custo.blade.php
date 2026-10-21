<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Centros de custo</h2>
    </x-slot>

<div x-data="{ openNewCostCenter: false }" class="max-w-7xl mx-auto px-6 py-8 space-y-6">
    <div class="flex items-end justify-between gap-4">
        <div>
            <p class="text-xs uppercase tracking-widest text-slate-500">Catálogos</p>
            <h1 class="text-3xl font-semibold text-slate-900">Centros de custo</h1>
            <p class="mt-1 text-sm text-slate-500">Catálogo usado para identificar o nome do centro informado no Active Directory.</p>
        </div>
        <button type="button" @click="openNewCostCenter = true" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Novo centro de custo</button>
    </div>

    @if(session('success'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Consultar centros de custo</h2>
                <p class="mt-1 text-sm text-slate-500">Pesquise pelo código ou pelo nome cadastrado.</p>
            </div>
        </div>
        <form method="GET" action="{{ route('cost-centers.index') }}" class="mt-4 flex flex-col gap-3 sm:flex-row">
            <input name="search" value="{{ $search }}" placeholder="Buscar por código ou nome" class="min-w-0 flex-1 rounded-md border-slate-300 text-sm">
            <button class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">Buscar</button>
            @if($search !== '')
                <a href="{{ route('cost-centers.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-center text-sm font-medium text-slate-700">Limpar</a>
            @endif
        </form>
    </section>

    <section class="rounded-lg border border-amber-200 bg-amber-50 p-5 shadow-sm">
        <h2 class="text-lg font-semibold text-amber-950">Códigos usados no AD sem nome cadastrado</h2>
        <p class="mt-1 text-sm text-amber-800">Cadastre o nome oficial para que e-mail, Office e demais relatórios consigam identificar o centro.</p>
        @if($missingCostCenters->isEmpty())
            <p class="mt-4 rounded-md bg-white/70 px-4 py-3 text-sm text-emerald-800">Nenhum código pendente encontrado para a busca atual.</p>
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[620px] text-left text-sm">
                    <thead class="border-b border-amber-200 text-xs uppercase text-amber-900"><tr><th class="px-3 py-3">Código</th><th class="px-3 py-3">Usuários no AD</th><th class="px-3 py-3">Cadastrar nome</th></tr></thead>
                    <tbody class="divide-y divide-amber-100">
                    @foreach($missingCostCenters as $missing)
                        <tr>
                            <td class="px-3 py-3 font-mono font-semibold text-amber-950">{{ $missing->code }}</td>
                            <td class="px-3 py-3 text-amber-900">{{ number_format($missing->users_total, 0, ',', '.') }}</td>
                            <td class="px-3 py-3"><form method="POST" action="{{ route('cost-centers.store') }}" class="flex min-w-[360px] gap-2">@csrf<input type="hidden" name="code" value="{{ $missing->code }}"><input name="name" required placeholder="Nome oficial do centro" class="min-w-0 flex-1 rounded-md border-amber-300 bg-white text-sm"><button class="rounded-md bg-slate-900 px-3 py-2 text-xs font-semibold text-white">Cadastrar</button></form></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Centros cadastrados</h2>
                <p class="mt-1 text-sm text-slate-500">Registros disponíveis para os módulos que usam rateio.</p>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ $centers->total() }} registro(s)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase text-slate-500"><tr><th class="px-3 py-3">Código</th><th class="px-3 py-3">Nome</th><th class="px-3 py-3">Status</th><th class="px-3 py-3 text-right">Ações</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($centers as $center)
                    <tr>
                        <td class="px-3 py-3 font-mono">{{ $center->code }}</td>
                        <td class="px-3 py-3">{{ $center->name }}</td>
                        <td class="px-3 py-3"><span class="rounded-full px-2 py-1 text-xs {{ $center->active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $center->active ? 'Ativo' : 'Inativo' }}</span></td>
                        <td class="px-3 py-3"><div class="flex justify-end gap-2">
                            <details class="relative"><summary class="cursor-pointer list-none rounded-md border border-slate-300 px-3 py-1.5 text-xs">Editar</summary><form method="POST" action="{{ route('cost-centers.update', $center) }}" style="width: 18rem; max-width: calc(100vw - 2rem);" class="absolute right-0 z-20 mt-2 rounded-md border border-slate-200 bg-white p-3 shadow-lg">@csrf @method('PATCH')<label class="mb-1 block text-xs font-medium text-slate-600">Código</label><input name="code" value="{{ $center->code }}" required class="mb-2 block w-full rounded border-slate-300 text-sm"><label class="mb-1 block text-xs font-medium text-slate-600">Nome</label><input name="name" value="{{ $center->name }}" required class="mb-2 block w-full rounded border-slate-300 text-sm"><button class="w-full rounded bg-slate-900 px-3 py-2 text-xs text-white">Salvar</button></form></details>
                            <form method="POST" action="{{ route('cost-centers.toggle', $center) }}">@csrf @method('PATCH')<button class="rounded-md border border-amber-300 px-3 py-1.5 text-xs text-amber-700">{{ $center->active ? 'Desativar' : 'Ativar' }}</button></form>
                            <form method="POST" action="{{ route('cost-centers.destroy', $center) }}" onsubmit="return confirm('Remover este centro do catálogo?')">@csrf @method('DELETE')<button class="rounded-md border border-rose-300 px-3 py-1.5 text-xs text-rose-700">Remover</button></form>
                        </div></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-3 py-8 text-center text-slate-500">Nenhum centro de custo cadastrado.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $centers->links() }}</div>
    </section>

    <div x-cloak x-show="openNewCostCenter" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4" @keydown.escape.window="openNewCostCenter = false">
        <div @click.outside="openNewCostCenter = false" style="width: min(100%, 32rem); max-height: calc(100vh - 2rem); overflow-y: auto;" class="flex-shrink-0 rounded-lg border border-slate-200 bg-white p-5 shadow-2xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Novo centro de custo</h2>
                    <p class="mt-1 text-sm text-slate-500">Cadastre o código e o nome oficial para uso no sistema.</p>
                </div>
                <button type="button" @click="openNewCostCenter = false" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700">Fechar</button>
            </div>
            <form method="POST" action="{{ route('cost-centers.store') }}" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label for="new-cost-center-code" class="mb-1 block text-sm font-medium text-slate-700">Código</label>
                    <input id="new-cost-center-code" name="code" required placeholder="Ex.: 01.157" class="w-full rounded-md border-slate-300 text-sm">
                </div>
                <div>
                    <label for="new-cost-center-name" class="mb-1 block text-sm font-medium text-slate-700">Nome</label>
                    <input id="new-cost-center-name" name="name" required placeholder="Nome do centro de custo" class="w-full rounded-md border-slate-300 text-sm">
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" @click="openNewCostCenter = false" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">Cancelar</button>
                    <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Adicionar</button>
                </div>
            </form>
        </div>
    </div>
</div>
</x-app-layout>
