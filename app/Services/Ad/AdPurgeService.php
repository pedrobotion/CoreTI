<?php

namespace App\Services\Ad;

use App\Models\AdPurgeBatch;
use App\Models\AdPurgeBatchItem;
use App\Models\AdUser;
use App\Models\User;
use App\Services\CoreTiSyncNotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ciclo de vida do expurgo mensal de contas do Active Directory.
 *
 * Fluxo: o servidor do AD monta a prévia e envia (registrarPrevia) → alguém
 * aprova conta a conta na tela (aprovar) → o servidor do AD busca o que foi
 * aprovado (loteLiberado) → executa e devolve o resultado (aplicarResultado).
 *
 * Nenhuma exclusão parte do CoreTI: aqui fica a decisão, não a credencial.
 */
class AdPurgeService
{
    public function __construct(
        private readonly CoreTiSyncNotificationService $notificacoes,
    ) {
    }

    /**
     * Registra (ou substitui) a prévia do mês.
     *
     * @param  array<int, array<string, mixed>>  $candidatos
     */
    public function registrarPrevia(
        string $referencia,
        array $candidatos,
        int $diasCriterio,
        ?string $origemHost = null,
        ?string $searchBase = null,
    ): AdPurgeBatch {
        return DB::transaction(function () use ($referencia, $candidatos, $diasCriterio, $origemHost, $searchBase) {
            $lote = AdPurgeBatch::firstOrNew(['referencia' => $referencia]);

            /*
             | Um lote já decidido não é sobrescrito. Reenviar a prévia depois
             | da aprovação apagaria a decisão registrada e as contas que já
             | foram excluídas — o histórico do mês deixaria de bater com o que
             | realmente aconteceu.
             */
            if ($lote->exists && ! $lote->pendente()) {
                return $lote;
            }

            $lote->fill([
                'status' => AdPurgeBatch::STATUS_AGUARDANDO,
                'dias_criterio' => $diasCriterio,
                'origem_host' => $origemHost,
                'search_base' => $searchBase,
                'total_candidatos' => count($candidatos),
                'total_aprovados' => 0,
                'total_excluidos' => 0,
                'expira_em' => null,
            ])->save();

            // Reenvio substitui a lista inteira: o que sumiu do AD desde a
            // última prévia não deve continuar listado.
            $lote->itens()->delete();

            $recusadas = [];

            foreach ($candidatos as $c) {
                $usuario = trim((string) ($c['nome_usuario'] ?? ''));

                if ($usuario === '') {
                    continue;
                }

                /*
                 | Segunda barreira, independente do servidor do AD: uma conta
                 | protegida que chegasse até a tela poderia ser aprovada por
                 | engano. Ela nem entra no lote.
                 */
                if ($this->nuncaExcluir($usuario)) {
                    $recusadas[] = $usuario;

                    continue;
                }

                $lote->itens()->create([
                    'nome_usuario' => $usuario,
                    'nome_exibicao' => $this->texto($c['nome_exibicao'] ?? null),
                    'email' => $this->texto($c['email'] ?? null),
                    'distinguished_name' => (string) ($c['distinguished_name'] ?? ''),
                    'desativada_em' => $this->data($c['desativada_em'] ?? null),
                    'dias_desativada' => isset($c['dias_desativada']) ? (int) $c['dias_desativada'] : null,
                    'ultimo_logon' => $this->data($c['ultimo_logon'] ?? null),
                    'descricao' => $this->texto($c['descricao'] ?? null),
                    'parece_servico' => (bool) ($c['parece_servico'] ?? false),
                    'ja_privilegiada' => (bool) ($c['ja_privilegiada'] ?? false),
                    'status' => AdPurgeBatchItem::STATUS_PENDENTE,
                ]);
            }

            if ($recusadas !== []) {
                Log::warning('ad.exclusao.candidato_protegido_recusado', [
                    'lote' => $lote->referencia,
                    'contas' => $recusadas,
                ]);
            }

            // O total precisa refletir o que entrou, não o que foi enviado.
            $lote->update(['total_candidatos' => $lote->itens()->count()]);

            $lote->refresh();

            $this->avisar($lote);

            return $lote;
        });
    }

    /**
     * Aprova apenas as contas escolhidas; as demais ficam recusadas.
     *
     * @param  array<int, int>  $itensAprovados  ids de ad_purge_batch_items
     */
    public function aprovar(AdPurgeBatch $lote, array $itensAprovados, User $usuario): AdPurgeBatch
    {
        if (! $lote->pendente()) {
            return $lote;
        }

        return DB::transaction(function () use ($lote, $itensAprovados, $usuario) {
            /*
             | Trava a linha e reconfere DENTRO da transação.
             |
             | A checagem acima acontece fora dela: dois cliques no botão, ou
             | duas pessoas conferindo ao mesmo tempo, passariam os dois pela
             | verificação e gravariam decisões diferentes sobre o mesmo lote —
             | a última sobrescrevendo a primeira, com contas aprovadas por
             | uma virando recusadas pela outra.
             */
            $atual = AdPurgeBatch::whereKey($lote->getKey())->lockForUpdate()->first();

            if ($atual === null || ! $atual->pendente()) {
                return $atual ?? $lote;
            }

            $lote = $atual;

            $ids = array_values(array_unique(array_map('intval', $itensAprovados)));

            // Restringe aos itens do próprio lote: id vindo do formulário não
            // pode alcançar conta de outro mês.
            $validos = $lote->itens()->whereIn('id', $ids)->pluck('id')->all();

            $lote->itens()->whereIn('id', $validos)
                ->update(['status' => AdPurgeBatchItem::STATUS_APROVADO]);

            $lote->itens()->whereNotIn('id', $validos ?: [0])
                ->update(['status' => AdPurgeBatchItem::STATUS_RECUSADO]);

            $total = count($validos);

            $lote->update([
                'status' => $total > 0 ? AdPurgeBatch::STATUS_APROVADO : AdPurgeBatch::STATUS_REJEITADO,
                'total_aprovados' => $total,
                'aprovado_por' => $usuario->id,
                'aprovado_em' => now(),
                'expira_em' => now()->addDays((int) config('ad.expurgo.validade_aprovacao_dias', 15)),
            ]);

            Log::info('ad.expurgo.aprovado', [
                'lote' => $lote->referencia,
                'aprovados' => $total,
                'recusados' => $lote->total_candidatos - $total,
                'por' => $usuario->id,
            ]);

            return $lote->refresh();
        });
    }

    public function rejeitar(AdPurgeBatch $lote, User $usuario, ?string $motivo = null): AdPurgeBatch
    {
        if (! $lote->pendente()) {
            return $lote;
        }

        $lote->itens()->update(['status' => AdPurgeBatchItem::STATUS_RECUSADO]);

        $lote->update([
            'status' => AdPurgeBatch::STATUS_REJEITADO,
            'total_aprovados' => 0,
            'rejeitado_por' => $usuario->id,
            'rejeitado_em' => now(),
            'observacao' => $motivo,
        ]);

        Log::info('ad.expurgo.rejeitado', ['lote' => $lote->referencia, 'por' => $usuario->id]);

        return $lote->refresh();
    }

    /**
     * Lote que o servidor do AD pode executar agora, se houver.
     */
    public function loteLiberado(): ?AdPurgeBatch
    {
        $lote = AdPurgeBatch::where('status', AdPurgeBatch::STATUS_APROVADO)
            ->orderBy('aprovado_em')
            ->first();

        if ($lote === null) {
            return null;
        }

        // Vencido não executa, mesmo que o agendador de expiração tenha falhado.
        if ($lote->venceu()) {
            $this->expirar($lote);

            return null;
        }

        return $lote;
    }

    /**
     * Aplica o resultado devolvido pelo servidor do AD.
     *
     * Marca as contas excluídas em ad_users preservando a linha — é o que
     * dispensa o passo manual do CSV.
     *
     * @param  array<int, array<string, mixed>>  $resultados
     * @return array{excluidos:int, pulados:int, falhas:int, restantes:int}
     */
    public function aplicarResultado(AdPurgeBatch $lote, array $resultados): array
    {
        return DB::transaction(function () use ($lote, $resultados) {
            $excluidos = 0;
            $pulados = 0;
            $falhas = 0;

            foreach ($resultados as $r) {
                $usuario = trim((string) ($r['nome_usuario'] ?? ''));

                if ($usuario === '') {
                    continue;
                }

                $item = $lote->itens()
                    ->whereRaw('LOWER(nome_usuario) = ?', [mb_strtolower($usuario)])
                    ->first();

                if ($item === null) {
                    Log::warning('ad.expurgo.resultado_fora_do_lote', [
                        'lote' => $lote->referencia,
                        'conta' => $usuario,
                    ]);

                    continue;
                }

                $situacao = (string) ($r['situacao'] ?? '');
                $detalhe = $this->texto($r['detalhe'] ?? null);

                if ($situacao === 'excluido') {
                    $quando = $this->data($r['excluido_em'] ?? null) ?? now();

                    $item->update([
                        'status' => AdPurgeBatchItem::STATUS_EXCLUIDO,
                        'excluido_em' => $quando,
                        'resultado_detalhe' => $detalhe,
                    ]);

                    $this->marcarNoInventario($usuario, $quando, $this->texto($r['excluido_por'] ?? null));
                    $excluidos++;

                    continue;
                }

                if ($situacao === 'pulado') {
                    $item->update([
                        'status' => AdPurgeBatchItem::STATUS_PULADO,
                        'resultado_detalhe' => $detalhe,
                    ]);
                    $pulados++;

                    continue;
                }

                $item->update([
                    'status' => AdPurgeBatchItem::STATUS_FALHOU,
                    'resultado_detalhe' => $detalhe,
                ]);
                $falhas++;
            }

            /*
             | O servidor do AD executa em lotes limitados para não sobrecarregar
             | a replicação. Enquanto sobrar item aprovado, o lote continua
             | liberado para a próxima chamada.
             */
            $restantes = $lote->itens()->aprovados()->count();

            $lote->increment('total_excluidos', $excluidos);

            if ($restantes === 0) {
                $lote->update([
                    'status' => AdPurgeBatch::STATUS_EXECUTADO,
                    'executado_em' => now(),
                ]);
            }

            Log::info('ad.expurgo.resultado', [
                'lote' => $lote->referencia,
                'excluidos' => $excluidos,
                'pulados' => $pulados,
                'falhas' => $falhas,
                'restantes' => $restantes,
            ]);

            return compact('excluidos', 'pulados', 'falhas', 'restantes');
        });
    }

    public function expirar(AdPurgeBatch $lote): void
    {
        $lote->update([
            'status' => AdPurgeBatch::STATUS_EXPIRADO,
            'observacao' => trim((string) $lote->observacao."\nAprovação vencida em ".optional($lote->expira_em)->format('d/m/Y H:i')),
        ]);

        Log::warning('ad.expurgo.expirado', ['lote' => $lote->referencia]);
    }

    /**
     * Marca a conta como excluída no inventário sem apagar a linha: o
     * histórico de quem tinha acesso, de qual centro de custo e quando saiu
     * some junto com a conta no AD.
     */
    private function marcarNoInventario(string $nomeUsuario, Carbon $quando, ?string $por): void
    {
        $conta = AdUser::whereRaw('LOWER(nome_usuario) = ?', [mb_strtolower($nomeUsuario)])->first();

        if ($conta === null) {
            return;
        }

        $conta->update([
            'excluido' => true,
            'excluido_em' => $quando,
            'excluido_por' => $por,
            'e_ativo' => false,
        ]);
    }

    private function avisar(AdPurgeBatch $lote): void
    {
        if ($lote->total_candidatos === 0) {
            return;
        }

        $this->notificacoes->notify(
            source: 'Exclusão de contas do Active Directory',
            summary: [
                'Competência' => $lote->referencia,
                'Contas na prévia' => $lote->total_candidatos,
                'Critério' => $lote->dias_criterio.' dias desativada',
            ],
            status: 'warning',
            message: 'Há um lote de exclusão de contas do Active Directory aguardando aprovação no CoreTI. '
                .'Nenhuma conta será excluída antes da conferência.',
        );
    }

    /**
     * Nome casa alguma regra de "nunca excluir"?
     *
     * Comparação sem diferenciar maiúsculas: renomear para KRBTGT_31402 não
     * pode driblar a proteção.
     */
    private function nuncaExcluir(string $nomeUsuario): bool
    {
        $nome = mb_strtolower($nomeUsuario);

        foreach ((array) config('ad.expurgo.nunca_excluir', []) as $padrao) {
            if (fnmatch(mb_strtolower((string) $padrao), $nome)) {
                return true;
            }
        }

        return false;
    }

    private function texto(mixed $valor): ?string
    {
        $t = trim((string) ($valor ?? ''));

        return $t === '' ? null : $t;
    }

    private function data(mixed $valor): ?Carbon
    {
        if (blank($valor)) {
            return null;
        }

        try {
            /*
             | O servidor do AD manda ISO 8601 com fuso (-03:00). Sem converter
             | para o fuso da aplicação, o Eloquent grava o horário local como
             | se já fosse UTC e a auditoria fica 3 horas atrasada.
             */
            return Carbon::parse((string) $valor)->setTimezone(config('app.timezone', 'UTC'));
        } catch (\Throwable) {
            return null;
        }
    }
}
