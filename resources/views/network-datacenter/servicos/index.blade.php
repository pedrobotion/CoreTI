<x-app-layout>
    <div
        class="mx-auto max-w-7xl space-y-6"
        x-data="{
            editModalOpen: false,
            editAction: '',
            emptyService: {
                action: '',
                id: '',
                contrato: '',
                contratante_id: '',
                razao_social: '',
                velocidade_nominal: '',
                produto_id: '',
                rede: '',
                mascara: '',
                gateway: '',
                broadcast: '',
                faixa: '',
                qtd: '',
                endereco_instalacao: '',
                estacao_a: '',
                endereco: '',
                numero: '',
                bairro: '',
                municipio: '',
                uf: '',
                contato: '',
                outros_contatos: '',
                organization_unit_id: '',
                organization_department_id: '',
                local_tipo: '',
                local_nome: '',
                local_busca: '',
                observacao: '',
                status: 'ativo'
            },
            editingService: {},
            openEditModal(service) {
                this.editingService = { ...this.emptyService, ...service };
                this.editAction = service.action || '';
                this.editModalOpen = true;
            },
            closeEditModal() {
                this.editModalOpen = false;
            }
        }"
        x-init="editingService = { ...emptyService }"
    >
        <div class="flex items-start justify-between gap-3">
            <a href="{{ route('network-datacenter.servicos.create') }}" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Novo serviço</a>
        </div>

        @if(!empty($setupPending))
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Estrutura do módulo ainda não foi criada no banco. Execute as migrations do Network &amp; DataCenter para liberar os cadastros.
            </div>
        @endif

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" class="flex flex-wrap items-end gap-2">
                <div class="min-w-[220px] flex-1">
                    <label class="text-xs font-semibold uppercase text-slate-500">Busca</label>
                    <input name="q" value="{{ $search }}" placeholder="Contrato, contratante, local, observação" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div class="min-w-[180px]">
                    <label class="text-xs font-semibold uppercase text-slate-500">Contrato</label>
                    <input name="contrato" value="{{ $filters['contrato'] ?? '' }}" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div class="min-w-[220px]">
                    <label class="text-xs font-semibold uppercase text-slate-500">Contratante</label>
                    <select name="contratante_id" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Todos</option>
                        @foreach($contratantes as $contratante)
                            <option value="{{ $contratante->id }}" @selected(($filters['contratante_id'] ?? null) == $contratante->id)>{{ $contratante->razao_social }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-[200px]">
                    <label class="text-xs font-semibold uppercase text-slate-500">Produto</label>
                    <select name="produto_id" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Todos</option>
                        @foreach($produtos as $produto)
                            <option value="{{ $produto->id }}" @selected(($filters['produto_id'] ?? null) == $produto->id)>{{ $produto->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-[200px]">
                    <label class="text-xs font-semibold uppercase text-slate-500">Local</label>
                    <input name="local" value="{{ $filters['local'] ?? '' }}" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div class="min-w-[180px]">
                    <label class="text-xs font-semibold uppercase text-slate-500">Município</label>
                    <input name="municipio" value="{{ $filters['municipio'] ?? '' }}" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div class="min-w-[140px]">
                    <label class="text-xs font-semibold uppercase text-slate-500">Status</label>
                    <select name="status" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Todos</option>
                        <option value="ativo" @selected(($filters['status'] ?? '') === 'ativo')>Ativo</option>
                        <option value="inativo" @selected(($filters['status'] ?? '') === 'inativo')>Inativo</option>
                    </select>
                </div>
                <div class="min-w-[140px]">
                    <label class="text-xs font-semibold uppercase text-slate-500">Por página</label>
                    <select name="per_page" class="mt-1 h-10 w-full rounded-md border-slate-300 text-sm">
                        @foreach([10, 15, 25, 50] as $option)
                            <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Buscar</button>
                    <a href="{{ route('network-datacenter.servicos.index') }}" class="inline-flex h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Limpar</a>
                </div>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Contrato</th>
                            <th class="px-5 py-3">Unidade</th>
                            <th class="px-5 py-3">IP</th>
                            <th class="px-5 py-3">Produto</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Ficha</th>
                            <th class="px-5 py-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($records as $record)
                            <tr>
                                <td class="px-5 py-3">{{ $record->contrato ?: '-' }}</td>
                                <td class="px-5 py-3">{{ $record->local_nome ?: '-' }}</td>
                                <td class="px-5 py-3">{{ $record->rede ?: '-' }}</td>
                                <td class="px-5 py-3">{{ $record->produto?->nome ?: '-' }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $record->status === 'ativo' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                                        {{ $record->status }}
                                    </span>
                                </td>
                                <td class="px-5 py-3">
                                    @if($record->ficha_storage_path)
                                        <span class="text-xs text-emerald-700">Anexada</span>
                                    @else
                                        <span class="text-xs text-slate-500">Sem ficha</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('network-datacenter.servicos.show', $record) }}" class="inline-flex rounded-md border border-[#033151] bg-white px-3 py-1.5 text-xs font-semibold text-[#033151] hover:bg-slate-50">Abrir</a>
                                        <button
                                            type="button"
                                            class="inline-flex rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                            @click="openEditModal(@js([
                                                'action' => url('/network-datacenter/servicos') . '/' . $record->id,
                                                'id' => $record->id,
                                                'contrato' => $record->contrato,
                                                'contratante_id' => $record->contratante_id,
                                                'razao_social' => $record->razao_social,
                                                'velocidade_nominal' => $record->velocidade_nominal,
                                                'produto_id' => $record->produto_id,
                                                'rede' => $record->rede,
                                                'mascara' => $record->mascara,
                                                'gateway' => $record->gateway,
                                                'broadcast' => $record->broadcast,
                                                'faixa' => $record->faixa,
                                                'qtd' => $record->qtd,
                                                'endereco_instalacao' => $record->endereco_instalacao,
                                                'estacao_a' => $record->estacao_a,
                                                'endereco' => $record->endereco,
                                                'numero' => $record->numero,
                                                'bairro' => $record->bairro,
                                                'municipio' => $record->municipio,
                                                'uf' => $record->uf,
                                                'contato' => $record->contato,
                                                'outros_contatos' => $record->outros_contatos,
                                                'organization_unit_id' => $record->organization_unit_id,
                                                'organization_department_id' => $record->organization_department_id,
                                                'local_tipo' => $record->local_tipo,
                                                'local_nome' => $record->local_nome,
                                                'local_busca' => $record->local_nome,
                                                'observacao' => $record->observacao,
                                                'status' => $record->status,
                                            ]))"
                                        >Editar</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-6 text-center text-slate-500">Nenhum serviço encontrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-4">
                {{ $records->links('vendor.pagination.cocari') }}
            </div>
        </section>

        <div
            x-cloak
            x-show="editModalOpen"
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 px-4 py-6"
            @keydown.escape.window="closeEditModal()"
            @click.self="closeEditModal()"
        >
            <div class="w-full max-w-4xl max-h-[calc(100vh-1.5rem)] overflow-hidden rounded-lg bg-white shadow-2xl flex flex-col">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">Editar serviço</h3>
                        <p class="text-sm text-slate-500">Altere apenas os campos necessários e salve.</p>
                    </div>
                    <button type="button" @click="closeEditModal()" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Fechar</button>
                </div>

                <form method="POST" :action="editAction" class="flex min-h-0 flex-1 flex-col">
                    @csrf
                    @method('PUT')

                    <input type="hidden" name="contratante_id" x-model="editingService.contratante_id">
                    <input type="hidden" name="razao_social" x-model="editingService.razao_social">
                    <input type="hidden" name="mascara" x-model="editingService.mascara">
                    <input type="hidden" name="gateway" x-model="editingService.gateway">
                    <input type="hidden" name="broadcast" x-model="editingService.broadcast">
                    <input type="hidden" name="faixa" x-model="editingService.faixa">
                    <input type="hidden" name="qtd" x-model="editingService.qtd">
                    <input type="hidden" name="endereco_instalacao" x-model="editingService.endereco_instalacao">
                    <input type="hidden" name="estacao_a" x-model="editingService.estacao_a">
                    <input type="hidden" name="endereco" x-model="editingService.endereco">
                    <input type="hidden" name="numero" x-model="editingService.numero">
                    <input type="hidden" name="bairro" x-model="editingService.bairro">
                    <input type="hidden" name="municipio" x-model="editingService.municipio">
                    <input type="hidden" name="uf" x-model="editingService.uf">
                    <input type="hidden" name="contato" x-model="editingService.contato">
                    <input type="hidden" name="outros_contatos" x-model="editingService.outros_contatos">
                    <input type="hidden" name="organization_unit_id" x-model="editingService.organization_unit_id">
                    <input type="hidden" name="organization_department_id" x-model="editingService.organization_department_id">
                    <input type="hidden" name="local_tipo" x-model="editingService.local_tipo">
                    <input type="hidden" name="local_nome" x-model="editingService.local_nome">

                    <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="text-sm font-medium text-slate-700">Contrato</label>
                                <input x-model="editingService.contrato" name="contrato" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Unidade</label>
                                <input x-model="editingService.local_busca" name="local_busca" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="Digite a unidade">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">IP</label>
                                <input x-model="editingService.rede" name="rede" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="192.168.0.0 /24">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Velocidade nominal</label>
                                <select x-model="editingService.velocidade_nominal" name="velocidade_nominal" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                    <option value="">Selecione</option>
                                    @foreach($velocidades as $velocidade)
                                        <option value="{{ $velocidade }}">{{ $velocidade }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Produto</label>
                                <select x-model="editingService.produto_id" name="produto_id" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                    <option value="">Selecione</option>
                                    @foreach($produtos as $produto)
                                        <option value="{{ $produto->id }}">{{ $produto->nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">Status</label>
                                <select x-model="editingService.status" name="status" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                    <option value="ativo">Ativo</option>
                                    <option value="inativo">Inativo</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="text-sm font-medium text-slate-700">Observação</label>
                                <textarea x-model="editingService.observacao" name="observacao" rows="3" class="mt-1 w-full rounded-md border-slate-300 text-sm"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-slate-200 px-5 py-4">
                        <button type="button" @click="closeEditModal()" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</button>
                        <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Salvar alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
