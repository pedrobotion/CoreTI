<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Bancada de Serviços</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">{{ $mode === 'create' ? 'Adicionar equipamento' : 'Editar equipamento' }}</h1>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <form method="POST" enctype="multipart/form-data" action="{{ $mode === 'create' ? route('bancada-servicos.assets.store') : route('bancada-servicos.assets.update', $asset) }}" class="grid gap-3 sm:grid-cols-2" x-data="{ origem: @js(old('origem_tipo', $asset->origem_tipo ?: 'unidade')) }">
                @csrf
                @if($mode === 'edit') @method('PUT') @endif
                <div>
                    <label class="text-sm font-medium text-slate-700">Tipo do equipamento</label>
                    <select name="tipo_equipamento" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Selecione</option>
                        @foreach(($equipmentTypeOptions ?? []) as $typeOption)
                            <option value="{{ $typeOption }}" @selected(old('tipo_equipamento', $asset->tipo_equipamento) === $typeOption)>{{ $typeOption }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Plaqueta</label>
                    <input name="plaqueta" value="{{ old('plaqueta', $asset->plaqueta) }}" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Data chegada</label>
                    <input type="date" name="data_chegada" value="{{ old('data_chegada', optional($asset->data_chegada)->format('Y-m-d') ?? now()->toDateString()) }}" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Origem</label>
                    <select name="origem_tipo" x-model="origem" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option value="unidade" @selected(old('origem_tipo', $asset->origem_tipo ?: 'unidade') === 'unidade')>Unidade</option>
                        <option value="sede" @selected(old('origem_tipo', $asset->origem_tipo) === 'sede')>Departamento da sede</option>
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Para Unidade, os campos de nota de entrada são obrigatórios.</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Unidade/Setor</label>
                    <select x-show="origem === 'unidade'" x-cloak name="unidade_setor" :required="origem === 'unidade'" :disabled="origem !== 'unidade'" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Selecione a unidade</option>
                        @foreach(($unitOptionsByOrigin['unidade'] ?? []) as $unitOption)
                            <option value="{{ $unitOption }}" @selected(old('unidade_setor', $asset->unidade_setor) === $unitOption)>{{ $unitOption }}</option>
                        @endforeach
                    </select>
                    <select x-show="origem === 'sede'" x-cloak name="unidade_setor" :required="origem === 'sede'" :disabled="origem !== 'sede'" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Selecione o departamento</option>
                        @foreach(($unitOptionsByOrigin['sede'] ?? []) as $unitOption)
                            <option value="{{ $unitOption }}" @selected(old('unidade_setor', $asset->unidade_setor) === $unitOption)>{{ $unitOption }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Status</label>
                    @if($mode === 'create')
                        <input value="Em bancada (automático)" readonly class="mt-1 w-full rounded-md border-slate-300 bg-slate-100 text-sm text-slate-600">
                    @else
                        <select name="status" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            <optgroup label="Operacionais">
                                @foreach(($statusOptions['operacionais'] ?? []) as $option)
                                    <option value="{{ $option }}" @selected(old('status', $asset->status ?: 'Em bancada') === $option)>{{ $option }}</option>
                                @endforeach
                            </optgroup>
                            <optgroup label="Arquivamento">
                                @foreach(($statusOptions['arquivamento'] ?? []) as $option)
                                    <option value="{{ $option }}" @selected(old('status', $asset->status ?: 'Em bancada') === $option)>{{ $option }}</option>
                                @endforeach
                            </optgroup>
                        </select>
                    @endif
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">TIC (opcional)</label>
                    <input name="tic" value="{{ old('tic', $asset->tic) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="text-sm font-medium text-slate-700">Observação</label>
                    <textarea name="observacao" rows="3" class="mt-1 w-full rounded-md border-slate-300 text-sm">{{ old('observacao', $asset->observacao) }}</textarea>
                </div>

                <div class="sm:col-span-2 rounded-md border border-amber-200 bg-amber-50 p-3 text-amber-900">
                    <p class="text-sm font-semibold">Controle de entrada fiscal (destaque obrigatório)</p>
                    <p class="text-xs">Equipamentos de unidade precisam de documento, número e valor da nota para entrada.</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Status da entrada</label>
                    <select name="entrada_status" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option value="Aguardando Entrada" @selected(old('entrada_status', $asset->entrada_status ?: 'Aguardando Entrada') === 'Aguardando Entrada')>Aguardando Entrada</option>
                        <option value="Entrada Realizada" @selected(old('entrada_status', $asset->entrada_status) === 'Entrada Realizada')>Entrada Realizada</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Documento da entrada</label>
                    <input name="nota_documento_entrada" value="{{ old('nota_documento_entrada', $asset->nota_documento_entrada) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Número da nota</label>
                    <input name="nota_numero_entrada" value="{{ old('nota_numero_entrada', $asset->nota_numero_entrada) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Valor da nota</label>
                    <input type="number" step="0.01" min="0" name="nota_valor_entrada" value="{{ old('nota_valor_entrada', $asset->nota_valor_entrada) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="text-sm font-medium text-slate-700">Anexo da nota</label>
                    <input type="file" name="nota_anexo_entrada" class="mt-1 w-full rounded-md border-slate-300 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-slate-900 file:px-3 file:py-2 file:text-white">
                </div>

                <div class="sm:col-span-2 border-t border-slate-200 pt-4">
                    <p class="text-sm font-semibold text-slate-800">Fluxo de aguardando peça</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Peça necessária</label>
                    <input name="peca_nome" value="{{ old('peca_nome', $asset->peca_nome) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Quantidade</label>
                    <input type="number" min="1" max="999" name="peca_quantidade" value="{{ old('peca_quantidade', $asset->peca_quantidade) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Origem da peça</label>
                    <select name="peca_origem" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Selecione</option>
                        <option value="cd" @selected(old('peca_origem', $asset->peca_origem) === 'cd')>Estoque do Centro de Distribuição</option>
                        <option value="compra_internet" @selected(old('peca_origem', $asset->peca_origem) === 'compra_internet')>Compra pela internet</option>
                        <option value="estoque_ti" @selected(old('peca_origem', $asset->peca_origem) === 'estoque_ti')>Estoque interno da TI</option>
                        <option value="dell" @selected(old('peca_origem', $asset->peca_origem) === 'dell')>Fornecedor Dell</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">ServiceTag (Dell)</label>
                    <input name="service_tag" value="{{ old('service_tag', $asset->service_tag) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="text-sm font-medium text-slate-700">Link da compra (internet)</label>
                    <input name="peca_link_compra" value="{{ old('peca_link_compra', $asset->peca_link_compra) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>

                <div class="sm:col-span-2 border-t border-slate-200 pt-4">
                    <p class="text-sm font-semibold text-slate-800">Retorno de terceiros</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="text-sm font-medium text-slate-700">Descrição do problema (obrigatória para status Terceiros)</label>
                    <textarea name="terceiros_problema" rows="2" class="mt-1 w-full rounded-md border-slate-300 text-sm">{{ old('terceiros_problema', $asset->terceiros_problema) }}</textarea>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Empresa terceirizada</label>
                    <input name="terceiros_empresa" value="{{ old('terceiros_empresa', $asset->terceiros_empresa) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Nota de Remessa</label>
                    <input name="terceiros_nota_remessa" value="{{ old('terceiros_nota_remessa', $asset->terceiros_nota_remessa) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Número OS (opcional)</label>
                    <input name="terceiros_os_numero" value="{{ old('terceiros_os_numero', $asset->terceiros_os_numero) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Resultado</label>
                    <select name="terceiros_resultado" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Selecione</option>
                        <option value="aprovada" @selected(old('terceiros_resultado', $asset->terceiros_resultado) === 'aprovada')>Manutenção aprovada</option>
                        <option value="negada" @selected(old('terceiros_resultado', $asset->terceiros_resultado) === 'negada')>Manutenção negada</option>
                        <option value="sem_conserto" @selected(old('terceiros_resultado', $asset->terceiros_resultado) === 'sem_conserto')>Sem conserto</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="text-sm font-medium text-slate-700">Nota de orçamento</label>
                    <textarea name="terceiros_nota_orcamento" rows="3" class="mt-1 w-full rounded-md border-slate-300 text-sm">{{ old('terceiros_nota_orcamento', $asset->terceiros_nota_orcamento) }}</textarea>
                </div>
                <div class="sm:col-span-2">
                    <label class="text-sm font-medium text-slate-700">Anexo do orçamento</label>
                    <input type="file" name="terceiros_orcamento_anexo" class="mt-1 w-full rounded-md border-slate-300 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-slate-900 file:px-3 file:py-2 file:text-white">
                </div>
                <div class="sm:col-span-2">
                    <label class="text-sm font-medium text-slate-700">Observações</label>
                    <textarea name="terceiros_observacoes" rows="2" class="mt-1 w-full rounded-md border-slate-300 text-sm">{{ old('terceiros_observacoes', $asset->terceiros_observacoes) }}</textarea>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Orçamento</label>
                    <select name="terceiros_orcamento_status" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option value="">Selecione</option>
                        <option value="aprovado" @selected(old('terceiros_orcamento_status', $asset->terceiros_orcamento_status) === 'aprovado')>Aprovado</option>
                        <option value="reprovado" @selected(old('terceiros_orcamento_status', $asset->terceiros_orcamento_status) === 'reprovado')>Reprovado</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Retorno registrado em</label>
                    <input value="{{ optional($asset->terceiros_retorno_em)->format('d/m/Y H:i') }}" readonly class="mt-1 w-full rounded-md border-slate-300 bg-slate-100 text-sm text-slate-600">
                </div>

                <div class="sm:col-span-2 border-t border-slate-200 pt-4">
                    <p class="text-sm font-semibold text-slate-800">Monitoramento de backup</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Localização backup</label>
                    <input name="backup_localizacao" value="{{ old('backup_localizacao', $asset->backup_localizacao) }}" placeholder="TI / Unidade / Setor" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Data formatado</label>
                    <input type="date" name="backup_data_formatado" value="{{ old('backup_data_formatado', optional($asset->backup_data_formatado)->format('Y-m-d')) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                        <input type="checkbox" name="backup_pronto_emprestimo" value="1" @checked(old('backup_pronto_emprestimo', $asset->backup_pronto_emprestimo))>
                        Pronto para empréstimo novamente
                    </label>
                </div>

                <div class="sm:col-span-2 flex gap-2 pt-2">
                    <button class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white">{{ $mode === 'create' ? 'Cadastrar' : 'Salvar alterações' }}</button>
                    <a href="{{ route('bancada-servicos.assets') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700">Voltar</a>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
