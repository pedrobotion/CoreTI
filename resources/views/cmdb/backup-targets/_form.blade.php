<div class="space-y-6">
    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Identificação do backup</h2>
            <p class="text-xs text-slate-500">O que o CoreTI espera encontrar neste servidor.</p>
        </div>
        <div class="grid gap-4 p-5 md:grid-cols-3">
            <div class="md:col-span-3">
                <label class="block text-xs font-semibold text-slate-600">Nome (opcional)</label>
                <input type="text" name="nome" value="{{ old('nome', $alvo->nome) }}" placeholder="Ex.: Backup diário dbmon" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600">Servidor *</label>
                <input type="text" name="host" value="{{ old('host', $alvo->host) }}" required placeholder="sdb1" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600">Banco *</label>
                <input type="text" name="database_name" value="{{ old('database_name', $alvo->database_name) }}" required placeholder="dbmon" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600">Tipo *</label>
                <select name="tipo_backup" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    @foreach(\App\Models\CmdbBackupTarget::TIPOS_BACKUP as $chave => $rotulo)
                        <option value="{{ $chave }}" @selected(old('tipo_backup', $alvo->tipo_backup) === $chave)>{{ $rotulo }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-3">
                <label class="block text-xs font-semibold text-slate-600">Arquivo esperado *</label>
                <input type="text" name="arquivo" value="{{ old('arquivo', $alvo->arquivo) }}" required placeholder="/backup/dbmon/dbmon.onl" class="mt-1 w-full rounded-md border-slate-300 font-mono text-sm">
                <p class="mt-1 text-xs text-slate-400">Caminho absoluto. Espaços e acentos são aceitos.</p>
            </div>
            <div class="md:col-span-3">
                <label class="block text-xs font-semibold text-slate-600">Log da tecnologia (opcional)</label>
                <input type="text" name="log_path" value="{{ old('log_path', $alvo->log_path) }}" placeholder="/caminho/base.lg" class="mt-1 w-full rounded-md border-slate-300 font-mono text-sm">
                <p class="mt-1 text-xs text-slate-400">
                    Para Progress, o .lg da base. Reservado para evolução — a análise do log ainda não está implementada;
                    a auditoria usa o arquivo de backup.
                </p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600">Método de verificação *</label>
                <select name="metodo_verificacao" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    @foreach($metodos as $chave => $rotulo)
                        <option value="{{ $chave }}" @selected(old('metodo_verificacao', $alvo->metodo_verificacao) === $chave)>{{ $rotulo }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-slate-600">Ativo no CMDB</label>
                <select name="asset_id" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    <option value="">— não vinculado —</option>
                    @foreach($ativos as $ativoCmdb)
                        <option value="{{ $ativoCmdb->id }}" @selected((int) old('asset_id', $alvo->asset_id) === $ativoCmdb->id)>
                            {{ $ativoCmdb->name ?? $ativoCmdb->hostname }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Quando o backup deve existir</h2>
            <p class="text-xs text-slate-500">Define quando auditar e a partir de quando considerar atrasado.</p>
        </div>
        <div class="grid gap-4 p-5 md:grid-cols-4">
            <div>
                <label class="block text-xs font-semibold text-slate-600">Periodicidade *</label>
                <select name="periodicidade" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    @foreach(\App\Models\CmdbBackupTarget::PERIODICIDADES as $chave => $rotulo)
                        <option value="{{ $chave }}" @selected(old('periodicidade', $alvo->periodicidade) === $chave)>{{ $rotulo }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600">Horário esperado</label>
                <input type="time" name="horario_esperado" value="{{ old('horario_esperado', $alvo->horario_esperado ? substr($alvo->horario_esperado, 0, 5) : '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600">Tolerância (horas)</label>
                <input type="number" name="tolerancia_horas" value="{{ old('tolerancia_horas', $alvo->tolerancia_horas ?? 6) }}" min="0" max="168" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600">Monitorar a partir de</label>
                <input type="date" name="monitorar_a_partir_de" value="{{ old('monitorar_a_partir_de', optional($alvo->monitorar_a_partir_de)->format('Y-m-d')) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                <p class="mt-1 text-xs text-slate-400">Janelas anteriores são ignoradas.</p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600">Idade máxima (horas)</label>
                <input type="number" name="max_idade_horas" value="{{ old('max_idade_horas', $alvo->max_idade_horas) }}" min="1" max="8760" placeholder="{{ config('backups.stale_after_hours', 36) }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                <p class="mt-1 text-xs text-slate-400">
                    Proteção sobre a idade real do arquivo — independente da janela acima.
                </p>
            </div>
        </div>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Conexão SSH</h2>
            <p class="text-xs text-slate-500">
                Em branco usa o padrão do sistema ({{ config('backups.ssh.user', 'coreti-monitor') }}@servidor:{{ config('backups.ssh.port', 22) }}).
                Autenticação por chave; senha nunca é armazenada.
            </p>
        </div>
        <div class="grid gap-4 p-5 md:grid-cols-3">
            <div>
                <label class="block text-xs font-semibold text-slate-600">Host / IP SSH</label>
                <input type="text" name="ssh_host" value="{{ old('ssh_host', $alvo->ssh_host) }}" placeholder="172.17.1.10" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600">Usuário SSH</label>
                <input type="text" name="ssh_user" value="{{ old('ssh_user', $alvo->ssh_user) }}" placeholder="{{ config('backups.ssh.user', 'coreti-monitor') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600">Porta</label>
                <input type="number" name="ssh_port" value="{{ old('ssh_port', $alvo->ssh_port) }}" min="1" max="65535" placeholder="22" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
        </div>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Tamanho esperado</h2>
        </div>
        <div class="grid gap-4 p-5 md:grid-cols-3">
            <div>
                <label class="block text-xs font-semibold text-slate-600">Estratégia *</label>
                <select name="estrategia_tamanho" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    <option value="automatico" @selected(old('estrategia_tamanho', $alvo->estrategia_tamanho) === 'automatico')>Automática (média histórica)</option>
                    <option value="manual" @selected(old('estrategia_tamanho', $alvo->estrategia_tamanho) === 'manual')>Manual</option>
                </select>
                <p class="mt-1 text-xs text-slate-400">
                    Automática: {{ (int) (config('backups.ssh.fracao_tamanho_minimo', 0.5) * 100) }}% da média das últimas 5 verificações saudáveis.
                </p>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600">Tamanho mínimo (MB)</label>
                <input type="number" step="0.01" name="min_tamanho_mb" value="{{ old('min_tamanho_mb', $alvo->min_tamanho_bytes ? round($alvo->min_tamanho_bytes / 1048576, 2) : '') }}" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                <p class="mt-1 text-xs text-slate-400">Se informado, prevalece sobre o histórico.</p>
            </div>
            <div class="flex items-end">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="hidden" name="ativo" value="0">
                    <input type="checkbox" name="ativo" value="1" @checked(old('ativo', $alvo->ativo ?? true)) class="rounded border-slate-300">
                    Alvo ativo (será auditado)
                </label>
            </div>
            <div class="md:col-span-3">
                <label class="block text-xs font-semibold text-slate-600">Observações</label>
                <textarea name="observacao" rows="2" class="mt-1 w-full rounded-md border-slate-300 text-sm">{{ old('observacao', $alvo->observacao) }}</textarea>
            </div>
        </div>
    </section>
</div>
