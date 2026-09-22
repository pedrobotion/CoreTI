@php
    /** @var \App\Models\AdmissionCase $case */
    $antecedencia = (int) config('admissao.antecedencia_recomendada_dias', 5);
@endphp

<div class="space-y-6">

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Identificação</h2>
            <p class="text-xs text-slate-500">Busque pela matrícula para trazer os dados já cadastrados e evitar redigitação.</p>
        </div>
        <div class="grid gap-4 p-5 md:grid-cols-3">
            <div class="md:col-span-3">
                <label class="block text-xs font-semibold text-slate-600">Buscar colaborador já cadastrado</label>
                <input
                    type="text"
                    id="colaborador-busca"
                    placeholder="Digite nome ou matrícula (mín. 3 caracteres)"
                    autocomplete="off"
                    class="mt-1 w-full rounded-md border-slate-300 text-sm"
                >
                <div id="colaborador-resultados" class="mt-1 hidden max-h-56 overflow-y-auto rounded-md border border-slate-200 bg-white text-sm shadow-lg"></div>
                <p class="mt-1 text-xs text-slate-400">Opcional. Se o colaborador ainda não estiver no Protheus, preencha manualmente abaixo.</p>
            </div>

            <input type="hidden" name="hr_collaborator_id" id="hr_collaborator_id" value="{{ old('hr_collaborator_id', $case->hr_collaborator_id) }}">

            <div>
                <label class="block text-xs font-semibold text-slate-600">Tipo *</label>
                <select name="tipo" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    <option value="admissao" @selected(old('tipo', $case->tipo) === 'admissao')>Admissão</option>
                    <option value="transferencia" @selected(old('tipo', $case->tipo) === 'transferencia')>Transferência</option>
                    <option value="promocao" @selected(old('tipo', $case->tipo) === 'promocao')>Promoção</option>
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-slate-600">Nome completo *</label>
                <input type="text" name="nome" id="campo-nome" value="{{ old('nome', $case->nome) }}" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600">Matrícula</label>
                <input type="text" name="matricula" id="campo-matricula" value="{{ old('matricula', $case->matricula) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600">CPF</label>
                <input type="text" name="cpf" value="{{ old('cpf', $case->cpf) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600">Telefone</label>
                <input type="text" name="telefone" value="{{ old('telefone', $case->telefone) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-slate-600">E-mail pessoal</label>
                <input type="email" name="email_pessoal" value="{{ old('email_pessoal', $case->email_pessoal) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                <p class="mt-1 text-xs text-slate-400">Usado para enviar as boas-vindas antes do primeiro dia.</p>
            </div>
        </div>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Lotação</h2>
            <p class="text-xs text-slate-500">Determina qual perfil de acesso será aplicado.</p>
        </div>
        <div class="grid gap-4 p-5 md:grid-cols-3">
            <div>
                <label class="block text-xs font-semibold text-slate-600">Cargo (código)</label>
                <input type="text" name="cargo_source_id" id="campo-cargo-id" value="{{ old('cargo_source_id', $case->cargo_source_id) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-slate-600">Cargo (descrição)</label>
                <input type="text" name="cargo_nome" value="{{ old('cargo_nome', $case->cargo_nome) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600">Área (código)</label>
                <input type="text" name="area_source_id" id="campo-area-id" value="{{ old('area_source_id', $case->area_source_id) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-slate-600">Área (descrição)</label>
                <input type="text" name="area_nome" value="{{ old('area_nome', $case->area_nome) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600">Unidade (código)</label>
                <input type="text" name="unidade_source_id" id="campo-unidade-id" value="{{ old('unidade_source_id', $case->unidade_source_id) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600">Unidade (nome)</label>
                <input type="text" name="unidade_nome" value="{{ old('unidade_nome', $case->unidade_nome) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600">Centro de custo</label>
                <input type="text" name="centro_custo" id="campo-cc" value="{{ old('centro_custo', $case->centro_custo) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600">Departamento (código)</label>
                <input type="text" name="departamento_source_id" id="campo-depto-id" value="{{ old('departamento_source_id', $case->departamento_source_id) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600">Gestor</label>
                <input type="text" name="gestor_nome" value="{{ old('gestor_nome', $case->gestor_nome) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                <input type="hidden" name="gestor_source_id" id="campo-gestor-id" value="{{ old('gestor_source_id', $case->gestor_source_id) }}">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600">E-mail do gestor</label>
                <input type="email" name="gestor_email" value="{{ old('gestor_email', $case->gestor_email) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
        </div>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Datas</h2>
            <p class="text-xs text-slate-500">Os prazos das tarefas são calculados a partir da data de início.</p>
        </div>
        <div class="grid gap-4 p-5 md:grid-cols-3">
            <div>
                <label class="block text-xs font-semibold text-slate-600">Data de admissão</label>
                <input type="date" name="data_admissao" value="{{ old('data_admissao', optional($case->data_admissao)->format('Y-m-d')) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600">Data de início *</label>
                <input type="date" name="data_inicio" value="{{ old('data_inicio', optional($case->data_inicio)->format('Y-m-d')) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                <p class="mt-1 text-xs text-slate-400">Ideal: registrar com pelo menos {{ $antecedencia }} dias de antecedência.</p>
            </div>
        </div>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Dados que só o RH possui</h2>
            <p class="text-xs text-slate-500">Sem isso, Patrimônio precisa perguntar de novo — que é justamente o retrabalho que este fluxo elimina.</p>
        </div>
        <div class="grid gap-4 p-5 md:grid-cols-3">
            <div>
                <label class="block text-xs font-semibold text-slate-600">Tamanho de uniforme</label>
                <input type="text" name="tamanho_uniforme" value="{{ old('tamanho_uniforme', $case->tamanho_uniforme) }}" placeholder="P / M / G / GG" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600">Tamanho de calçado</label>
                <input type="text" name="tamanho_calcado" value="{{ old('tamanho_calcado', $case->tamanho_calcado) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600">EPI necessário</label>
                <input type="text" name="epi[]" value="{{ old('epi.0', is_array($case->epi) ? ($case->epi[0] ?? '') : '') }}" placeholder="Ex.: botina, luva" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600">Substitui (matrícula)</label>
                <input type="text" name="substitui_matricula" value="{{ old('substitui_matricula', $case->substitui_matricula) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-slate-600">Substitui (nome)</label>
                <input type="text" name="substitui_nome" value="{{ old('substitui_nome', $case->substitui_nome) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                <p class="mt-1 text-xs text-slate-400">Quando informado, a TI espelha os acessos do colaborador substituído.</p>
            </div>

            <div class="md:col-span-3">
                <label class="block text-xs font-semibold text-slate-600">Observações</label>
                <textarea name="observacoes" rows="3" class="mt-1 w-full rounded-md border-slate-300 text-sm">{{ old('observacoes', $case->observacoes) }}</textarea>
            </div>
        </div>
    </section>
</div>

@push('scripts')
<script>
(() => {
    const busca = document.getElementById('colaborador-busca');
    const resultados = document.getElementById('colaborador-resultados');
    if (!busca || !resultados) return;

    const campos = {
        hr_collaborator_id: 'id',
        'campo-nome': 'nome',
        'campo-matricula': 'matricula',
        'campo-cargo-id': 'cargo_source_id',
        'campo-area-id': 'area_source_id',
        'campo-depto-id': 'departamento_source_id',
        'campo-unidade-id': 'unidade_source_id',
        'campo-cc': 'centro_custo',
        'campo-gestor-id': 'gestor_source_id',
    };

    let timer = null;

    busca.addEventListener('input', () => {
        clearTimeout(timer);
        const termo = busca.value.trim();
        if (termo.length < 3) { resultados.classList.add('hidden'); return; }

        timer = setTimeout(async () => {
            try {
                const url = '{{ route('admissao.collaborators.lookup') }}?q=' + encodeURIComponent(termo);
                const resposta = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!resposta.ok) return;
                const { data } = await resposta.json();

                resultados.innerHTML = '';
                if (!data.length) {
                    resultados.innerHTML = '<p class="px-3 py-2 text-slate-500">Nenhum colaborador encontrado. Preencha manualmente.</p>';
                } else {
                    data.forEach((item) => {
                        const linha = document.createElement('button');
                        linha.type = 'button';
                        linha.className = 'block w-full px-3 py-2 text-left hover:bg-slate-50';
                        linha.innerHTML = '<span class="font-medium text-slate-800"></span><span class="ml-2 text-xs text-slate-400"></span>';
                        linha.querySelector('span').textContent = item.nome;
                        linha.querySelectorAll('span')[1].textContent = 'matrícula ' + (item.matricula || '-');
                        linha.addEventListener('click', () => {
                            Object.entries(campos).forEach(([elId, chave]) => {
                                const el = document.getElementById(elId);
                                if (el && item[chave] != null) el.value = item[chave];
                            });
                            resultados.classList.add('hidden');
                            busca.value = item.nome;
                        });
                        resultados.appendChild(linha);
                    });
                }
                resultados.classList.remove('hidden');
            } catch (e) { /* silencioso: o preenchimento manual continua disponível */ }
        }, 300);
    });

    document.addEventListener('click', (e) => {
        if (!resultados.contains(e.target) && e.target !== busca) resultados.classList.add('hidden');
    });
})();
</script>
@endpush
