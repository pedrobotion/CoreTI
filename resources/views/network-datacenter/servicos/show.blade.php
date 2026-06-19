<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Network &amp; DataCenter</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-950">Serviço de Conectividade</h1>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('network-datacenter.servicos.edit', $servico) }}" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Editar</a>
                <a href="{{ route('network-datacenter.servicos.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Voltar</a>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">Dados do serviço</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold uppercase text-slate-500">Contrato</dt><dd class="mt-1 text-sm text-slate-900">{{ $servico->contrato ?: '-' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-slate-500">Contratante</dt><dd class="mt-1 text-sm text-slate-900">{{ $servico->contratante?->razao_social ?: '-' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-slate-500">Razão social</dt><dd class="mt-1 text-sm text-slate-900">{{ $servico->razao_social ?: '-' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-slate-500">Velocidade</dt><dd class="mt-1 text-sm text-slate-900">{{ $servico->velocidade_nominal ?: '-' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-slate-500">Produto</dt><dd class="mt-1 text-sm text-slate-900">{{ $servico->produto?->nome ?: '-' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-slate-500">Status</dt><dd class="mt-1 text-sm text-slate-900">{{ $servico->status ?: '-' }}</dd></div>
                </dl>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">Ficha de ativação</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold uppercase text-slate-500">Arquivo</dt><dd class="mt-1 text-sm text-slate-900">{{ $servico->ficha_original_name ?: 'Nenhuma ficha anexada' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-slate-500">Enviada em</dt><dd class="mt-1 text-sm text-slate-900">{{ optional($servico->ficha_uploaded_at)->format('d/m/Y H:i') ?: '-' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-slate-500">Enviada por</dt><dd class="mt-1 text-sm text-slate-900">{{ $servico->fichaUploader?->name ?: '-' }}</dd></div>
                </dl>

                <div class="mt-4 flex flex-wrap gap-2">
                    @if($servico->ficha_storage_path)
                        <form method="POST" action="{{ route('network-datacenter.servicos.ficha.upload', $servico) }}" enctype="multipart/form-data" class="flex flex-wrap gap-2">
                            @csrf
                            <input type="file" name="ficha_pdf" accept="application/pdf" class="block rounded-md border border-slate-300 bg-white text-sm">
                            <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Substituir ficha</button>
                        </form>
                        <a href="{{ route('network-datacenter.servicos.ficha.download', $servico) }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Baixar ficha</a>
                    @else
                        <form method="POST" action="{{ route('network-datacenter.servicos.ficha.upload', $servico) }}" enctype="multipart/form-data" class="flex flex-wrap gap-2">
                            @csrf
                            <input type="file" name="ficha_pdf" accept="application/pdf" class="block rounded-md border border-slate-300 bg-white text-sm" required>
                            <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#033151] px-4 text-sm font-semibold text-white hover:opacity-90">Anexar ficha</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('network-datacenter.servicos.ficha.extract', $servico) }}">
                        @csrf
                        <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Preencher dados com IA</button>
                    </form>
                </div>
            </section>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">Dados de rede e instalação</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div><p class="text-xs font-semibold uppercase text-slate-500">Rede</p><p class="mt-1 text-sm text-slate-900">{{ $servico->rede ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Máscara</p><p class="mt-1 text-sm text-slate-900">{{ $servico->mascara ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Gateway</p><p class="mt-1 text-sm text-slate-900">{{ $servico->gateway ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Broadcast</p><p class="mt-1 text-sm text-slate-900">{{ $servico->broadcast ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Faixa</p><p class="mt-1 text-sm text-slate-900">{{ $servico->faixa ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">QTD</p><p class="mt-1 text-sm text-slate-900">{{ $servico->qtd ?: '-' }}</p></div>
                <div class="sm:col-span-2 lg:col-span-3"><p class="text-xs font-semibold uppercase text-slate-500">Endereço de instalação</p><p class="mt-1 text-sm text-slate-900 whitespace-pre-line">{{ $servico->endereco_instalacao ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Local</p><p class="mt-1 text-sm text-slate-900">{{ $servico->local_tipo ? ($servico->local_tipo . ' - ' . $servico->local_nome) : ($servico->local_nome ?: '-') }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Estação A</p><p class="mt-1 text-sm text-slate-900">{{ $servico->estacao_a ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Município</p><p class="mt-1 text-sm text-slate-900">{{ $servico->municipio ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">UF</p><p class="mt-1 text-sm text-slate-900">{{ $servico->uf ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Contato</p><p class="mt-1 text-sm text-slate-900">{{ $servico->contato ?: '-' }}</p></div>
                <div class="sm:col-span-2 lg:col-span-3"><p class="text-xs font-semibold uppercase text-slate-500">Outros contatos</p><p class="mt-1 text-sm text-slate-900 whitespace-pre-line">{{ $servico->outros_contatos ?: '-' }}</p></div>
                <div class="sm:col-span-2 lg:col-span-3"><p class="text-xs font-semibold uppercase text-slate-500">Observação</p><p class="mt-1 text-sm text-slate-900 whitespace-pre-line">{{ $servico->observacao ?: '-' }}</p></div>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">Extração por IA</h2>
            <p class="mt-1 text-sm text-slate-600">Status atual: <span class="font-semibold">{{ $servico->ia_status ?: 'não processado' }}</span></p>
            <pre class="mt-4 overflow-x-auto rounded-md bg-slate-50 p-4 text-xs text-slate-700">{{ json_encode($servico->ia_resultado ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </section>
    </div>
</x-app-layout>
