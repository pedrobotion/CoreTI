<section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5 sm:px-6">
        <div class="flex items-center gap-3">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-teal-50 text-teal-600">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 21h18M5 21V7l6-4v18M19 21V11l-8-4M8 10h.01M8 14h.01"/></svg>
            </span>
            <div>
                <h2 class="text-base font-bold text-slate-900">Empresas Terceirizadas</h2>
                <p class="mt-0.5 text-sm text-slate-500">Cadastro das empresas que prestam serviço aos equipamentos.</p>
            </div>
        </div>
        <span class="inline-flex items-center rounded-full bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-700 ring-1 ring-inset ring-teal-200">{{ $activeThirdPartyCompaniesCount ?? 0 }} {{ ($activeThirdPartyCompaniesCount ?? 0) === 1 ? 'ativa' : 'ativas' }}</span>
    </div>

    <div class="border-b border-slate-100 bg-slate-50/60 p-5 sm:px-6">
        <form method="POST" action="{{ route('bancada-servicos.admin.third-party-companies.store') }}" class="grid gap-3 sm:grid-cols-3">
            @csrf
            <input name="name" placeholder="Nome da empresa" required class="h-11 rounded-xl border-slate-200 text-sm focus:border-[#033151] focus:ring-[#033151]/20">
            <input name="cnpj" placeholder="CNPJ" class="h-11 rounded-xl border-slate-200 text-sm focus:border-[#033151] focus:ring-[#033151]/20">
            <div class="flex gap-2">
                <input name="contact" placeholder="Contato (opcional)" class="h-11 w-full rounded-xl border-slate-200 text-sm focus:border-[#033151] focus:ring-[#033151]/20">
                <button class="inline-flex h-11 shrink-0 items-center gap-1.5 rounded-xl border border-[#033151] bg-[#033151] px-4 text-sm font-semibold text-white transition hover:opacity-90">
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14" stroke-linecap="round"/></svg>Adicionar
                </button>
            </div>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[820px] text-sm">
            <thead>
                <tr class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                    <th class="px-5 py-3 sm:px-6">Empresa</th><th class="px-5 py-3">CNPJ</th><th class="px-5 py-3">Contato</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse(($allThirdPartyCompanies ?? $thirdPartyCompanies ?? collect()) as $company)
                    <tr x-data="{ editModalOpen: false }" class="transition hover:bg-slate-50">
                        <td class="px-5 py-3 font-semibold text-slate-800 sm:px-6">{{ $company->name }}</td>
                        <td class="px-5 py-3 font-mono text-xs text-slate-500">{{ $company->cnpj ?: '—' }}</td>
                        <td class="px-5 py-3 text-slate-600">{{ $company->contact ?: '—' }}</td>
                        <td class="px-5 py-3">@if($company->is_active)<span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-200"><span class="h-1.5 w-1.5 rounded-full bg-current"></span>Ativa</span>@else<span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500 ring-1 ring-inset ring-slate-200"><span class="h-1.5 w-1.5 rounded-full bg-current"></span>Inativa</span>@endif</td>
                        <td class="px-5 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" @click="editModalOpen = true" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-slate-300">Editar</button>
                                <form method="POST" action="{{ route('bancada-servicos.admin.third-party-companies.toggle', $company) }}" onsubmit="return confirm('{{ $company->is_active ? 'Tem certeza que deseja desativar esta empresa terceirizada?' : 'Tem certeza que deseja ativar esta empresa terceirizada?' }}');">
                                    @csrf
                                    @method('PATCH')
                                    <button class="rounded-lg border px-3 py-1.5 text-xs font-semibold {{ $company->is_active ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700' }}">
                                        {{ $company->is_active ? 'Desativar' : 'Ativar' }}
                                    </button>
                                </form>
                            </div>
                            <div x-cloak x-show="editModalOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 px-4 text-left" @keydown.escape.window="editModalOpen=false">
                                <div class="w-full max-w-xl overflow-hidden rounded-2xl bg-white shadow-2xl" @click.outside="editModalOpen=false" @click.stop>
                                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                                        <h3 class="text-lg font-bold text-slate-900">Editar empresa terceirizada</h3>
                                        <button type="button" @click.stop="editModalOpen=false" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Fechar</button>
                                    </div>
                                    <form method="POST" action="{{ route('bancada-servicos.admin.third-party-companies.update', $company) }}" class="grid gap-3 px-5 py-4 sm:grid-cols-2">
                                        @csrf
                                        @method('PATCH')
                                        <div class="sm:col-span-2"><label class="text-sm font-medium">Nome da empresa</label><input name="name" value="{{ $company->name }}" required class="mt-1 w-full rounded-md border-slate-300 text-sm"></div>
                                        <div><label class="text-sm font-medium">CNPJ</label><input name="cnpj" value="{{ $company->cnpj }}" class="mt-1 w-full rounded-md border-slate-300 text-sm"></div>
                                        <div><label class="text-sm font-medium">Contato</label><input name="contact" value="{{ $company->contact }}" class="mt-1 w-full rounded-md border-slate-300 text-sm"></div>
                                        <div class="sm:col-span-2"><label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($company->is_active)> Ativa</label></div>
                                        <div class="sm:col-span-2 flex justify-end gap-2 border-t border-slate-200 pt-3">
                                            <button type="button" @click.stop="editModalOpen=false" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</button>
                                            <button class="inline-flex min-h-10 items-center justify-center rounded-lg border border-[#033151] bg-[#033151] px-4 text-sm font-semibold text-white transition hover:opacity-90">Salvar</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-6 py-10 text-center text-sm text-slate-400">Nenhuma empresa terceirizada cadastrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
