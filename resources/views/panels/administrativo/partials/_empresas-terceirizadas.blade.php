<section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
    <div class="mb-3 flex items-center justify-between">
        <h2 class="text-base font-bold text-slate-900">Empresas Terceirizadas</h2>
        <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">{{ $activeThirdPartyCompaniesCount ?? 0 }} {{ ($activeThirdPartyCompaniesCount ?? 0) === 1 ? 'ativa' : 'ativas' }}</span>
    </div>
    <form method="POST" action="{{ route('bancada-servicos.admin.third-party-companies.store') }}" class="grid gap-3 sm:grid-cols-3">@csrf<input name="name" placeholder="Nome da empresa" required class="rounded-md border-slate-300 text-sm"><input name="cnpj" placeholder="CNPJ" class="rounded-md border-slate-300 text-sm"><div class="flex gap-2"><input name="contact" placeholder="Contato (opcional)" class="w-full rounded-md border-slate-300 text-sm"><button class="rounded-md border border-[#033151] bg-[#033151] px-4 text-sm font-semibold text-white shadow-sm transition hover:opacity-90">Adicionar</button></div></form>
    <div class="mt-4 overflow-x-auto">
        <table class="w-full min-w-[820px] text-sm">
            <thead class="bg-slate-900 text-left text-xs font-semibold uppercase text-white"><tr><th class="px-3 py-2">Empresa</th><th class="px-3 py-2">CNPJ</th><th class="px-3 py-2">Contato</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Ações</th></tr></thead>
            <tbody class="divide-y divide-slate-200">
                @forelse(($allThirdPartyCompanies ?? $thirdPartyCompanies ?? collect()) as $company)
                    <tr x-data="{ editModalOpen: false }">
                        <td class="px-3 py-2">{{ $company->name }}</td>
                        <td class="px-3 py-2">{{ $company->cnpj ?: '-' }}</td>
                        <td class="px-3 py-2">{{ $company->contact ?: '-' }}</td>
                        <td class="px-3 py-2">@if($company->is_active)<span class="inline-flex rounded-md bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800">Ativa</span>@else<span class="inline-flex rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">Inativa</span>@endif</td>
                        <td class="px-3 py-2">
                            <div class="inline-flex items-center gap-2">
                                <button type="button" @click="editModalOpen = true" class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">Editar</button>
                                <form method="POST" action="{{ route('bancada-servicos.admin.third-party-companies.toggle', $company) }}" onsubmit="return confirm('{{ $company->is_active ? 'Tem certeza que deseja desativar esta empresa terceirizada?' : 'Tem certeza que deseja ativar esta empresa terceirizada?' }}');">
                                    @csrf
                                    @method('PATCH')
                                    <button class="rounded-md border px-2 py-1 text-xs font-semibold {{ $company->is_active ? 'border-rose-300 bg-rose-50 text-rose-700' : 'border-emerald-300 bg-emerald-50 text-emerald-700' }}">
                                        {{ $company->is_active ? 'Desativar' : 'Ativar' }}
                                    </button>
                                </form>
                            </div>
                            <div x-cloak x-show="editModalOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 px-4" @keydown.escape.window="editModalOpen=false">
                                <div class="w-full max-w-xl overflow-hidden rounded-lg bg-white shadow-2xl" @click.outside="editModalOpen=false" @click.stop>
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
                                            <button type="button" @click.stop="editModalOpen=false" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</button>
                                            <button class="inline-flex min-h-10 items-center justify-center rounded-md border border-[#033151] bg-[#033151] px-4 text-sm font-semibold text-white shadow-sm transition hover:opacity-90">Salvar</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-4 text-center text-slate-500">Nenhuma empresa terceirizada cadastrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
