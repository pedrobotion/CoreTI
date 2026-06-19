@php
    $servico = $servico ?? null;
    $isEdit = filled($servico?->id);
    $contratanteSelected = old('contratante_id', $servico?->contratante_id);
    $locationValue = old('local_busca', $servico?->local_nome ? (($servico->local_tipo === 'unidade' ? $servico->local_nome : $servico->local_nome) ?: '') : '');
    $razaoSocialValue = old('razao_social', $servico?->razao_social);
@endphp

<section
    x-data="{
        contratantes: @js($contratantes->mapWithKeys(fn ($item) => [$item->id => ['razao_social' => $item->razao_social]])->all()),
        locations: @js($locationOptions ?? []),
        razaoSocial: @js($razaoSocialValue ?? ''),
        localBusca: @js($locationValue ?? ''),
        localTipo: @js(old('local_tipo', $servico?->local_tipo)),
        localNome: @js(old('local_nome', $servico?->local_nome)),
        unidadeId: @js(old('unidade_id', $servico?->unidade_id)),
        departamentoId: @js(old('departamento_id', $servico?->departamento_id)),
        enderecoInstalacao: @js(old('endereco_instalacao', $servico?->endereco_instalacao)),
        fillContratante(id) {
            this.razaoSocial = this.contratantes[id]?.razao_social || '';
        },
        resolveLocation(value) {
            const needle = (value || '').toString().trim().toLowerCase();
            if (!needle) {
                return;
            }
            const normalized = needle.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            const match = this.locations.find((location) => {
                const label = (location.label || '').toLowerCase();
                const name = (location.name || '').toLowerCase();
                const search = (location.search || '').toLowerCase();
                return [label, name, search].some((item) => item === needle || item.includes(needle) || needle.includes(item) || item === normalized || item.includes(normalized));
            });
            if (!match) {
                return;
            }
            this.localTipo = match.type || '';
            this.localNome = match.name || '';
            this.unidadeId = match.id_unidade || '';
            this.departamentoId = match.id_departamento || '';
            if (match.endereco) {
                this.enderecoInstalacao = match.endereco;
            }
            this.localBusca = match.label || match.name || value;
        }
    }"
    x-init="if (localBusca) resolveLocation(localBusca)"
    class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"
>
    <form
        method="POST"
        action="{{ $isEdit ? route('network-datacenter.servicos.update', $servico) : route('network-datacenter.servicos.store') }}"
        enctype="multipart/form-data"
        class="space-y-6"
    >
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="text-sm font-medium text-slate-700">Contrato</label>
                <input name="contrato" value="{{ old('contrato', $servico?->contrato) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Contratante</label>
                <select name="contratante_id" x-on:change="fillContratante($event.target.value)" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    <option value="">Selecione</option>
                    @foreach($contratantes as $contratante)
                        <option value="{{ $contratante->id }}" @selected((string) $contratanteSelected === (string) $contratante->id)>{{ $contratante->razao_social }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Razão social</label>
                <input name="razao_social" x-model="razaoSocial" value="{{ old('razao_social', $servico?->razao_social) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Velocidade nominal</label>
                <select name="velocidade_nominal" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    <option value="">Selecione</option>
                    @foreach($velocidades as $velocidade)
                        <option value="{{ $velocidade }}" @selected(old('velocidade_nominal', $servico?->velocidade_nominal) === $velocidade)>{{ $velocidade }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Produto</label>
                <select name="produto_id" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    <option value="">Selecione</option>
                    @foreach($produtos as $produto)
                        <option value="{{ $produto->id }}" @selected(old('produto_id', $servico?->produto_id) == $produto->id)>{{ $produto->nome }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <label class="text-sm font-medium text-slate-700">Rede</label>
                <input name="rede" value="{{ old('rede', $servico?->rede) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="192.168.0.0">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Máscara</label>
                <input name="mascara" value="{{ old('mascara', $servico?->mascara) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="255.255.255.0">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Gateway</label>
                <input name="gateway" value="{{ old('gateway', $servico?->gateway) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="192.168.0.1">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Broadcast</label>
                <input name="broadcast" value="{{ old('broadcast', $servico?->broadcast) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="192.168.0.255">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Faixa</label>
                <input name="faixa" value="{{ old('faixa', $servico?->faixa) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="192.168.0.1 - 192.168.0.254">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">QTD</label>
                <input name="qtd" value="{{ old('qtd', $servico?->qtd) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
            <h3 class="text-sm font-semibold text-slate-900">Local/Unidade</h3>
            <p class="mt-1 text-xs text-slate-500">Selecione um local conhecido para preencher automaticamente o endereço quando houver cadastro base.</p>
            <div class="mt-3 grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="text-sm font-medium text-slate-700">Local/Unidade</label>
                    <input
                        x-model="localBusca"
                        x-on:change="resolveLocation($event.target.value)"
                        list="network-datacenter-locations"
                        name="local_busca"
                        placeholder="Digite ou selecione um local"
                        class="mt-1 w-full rounded-md border-slate-300 text-sm"
                    >
                    <datalist id="network-datacenter-locations">
                        @foreach($locationOptions as $location)
                            <option value="{{ $location['label'] }}"></option>
                        @endforeach
                    </datalist>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">Endereço de instalação</label>
                    <textarea name="endereco_instalacao" x-model="enderecoInstalacao" rows="3" class="mt-1 w-full rounded-md border-slate-300 text-sm">{{ old('endereco_instalacao', $servico?->endereco_instalacao) }}</textarea>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-slate-700">Estação A</label>
                        <input name="estacao_a" value="{{ old('estacao_a', $servico?->estacao_a) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">UF</label>
                        <input name="uf" value="{{ old('uf', $servico?->uf) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm" maxlength="10">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Endereço</label>
                        <input name="endereco" value="{{ old('endereco', $servico?->endereco) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Número</label>
                        <input name="numero" value="{{ old('numero', $servico?->numero) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Bairro</label>
                        <input name="bairro" value="{{ old('bairro', $servico?->bairro) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Município</label>
                        <input name="municipio" value="{{ old('municipio', $servico?->municipio) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    </div>
                </div>
                <input type="hidden" name="local_tipo" x-model="localTipo" value="{{ old('local_tipo', $servico?->local_tipo) }}">
                <input type="hidden" name="local_nome" x-model="localNome" value="{{ old('local_nome', $servico?->local_nome) }}">
                <input type="hidden" name="unidade_id" x-model="unidadeId" value="{{ old('unidade_id', $servico?->unidade_id) }}">
                <input type="hidden" name="departamento_id" x-model="departamentoId" value="{{ old('departamento_id', $servico?->departamento_id) }}">
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="text-sm font-medium text-slate-700">Contato</label>
                <input name="contato" value="{{ old('contato', $servico?->contato) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Status</label>
                <select name="status" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    <option value="ativo" @selected(old('status', $servico?->status ?? 'ativo') === 'ativo')>Ativo</option>
                    <option value="inativo" @selected(old('status', $servico?->status) === 'inativo')>Inativo</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="text-sm font-medium text-slate-700">Outros contatos</label>
                <textarea name="outros_contatos" rows="3" class="mt-1 w-full rounded-md border-slate-300 text-sm">{{ old('outros_contatos', $servico?->outros_contatos) }}</textarea>
            </div>
            <div class="md:col-span-2">
                <label class="text-sm font-medium text-slate-700">Observação</label>
                <textarea name="observacao" rows="3" class="mt-1 w-full rounded-md border-slate-300 text-sm">{{ old('observacao', $servico?->observacao) }}</textarea>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="text-sm font-medium text-slate-700">Ficha de ativação PDF</label>
                <input type="file" name="ficha_pdf" accept="application/pdf" class="mt-1 block w-full rounded-md border border-slate-300 bg-white text-sm">
                <p class="mt-1 text-xs text-slate-500">Pode ser anexada agora ou depois na tela de detalhes.</p>
            </div>
            <div class="flex items-end justify-end gap-2">
                <a href="{{ $isEdit ? route('network-datacenter.servicos.show', $servico) : route('network-datacenter.servicos.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</a>
                <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">
                    {{ $isEdit ? 'Salvar alterações' : 'Cadastrar serviço' }}
                </button>
            </div>
        </div>
    </form>
</section>
