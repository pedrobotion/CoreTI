<?php

namespace App\Services;

use App\Models\AdPurgeBatch;
use App\Models\BancadaCdRequisicao;
use App\Models\CoreTiNotificationRecipient;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Alertas REAIS (não a configuração) para o sino do topo: o que está
 * acontecendo agora e merece atenção — sincronizações com falha/atraso/aviso e
 * lotes de exclusão de contas do AD aguardando aprovação — filtrados pelos tipos
 * de aviso em que o usuário está cadastrado. Roda em toda página: é defensivo
 * (nunca quebra o layout) e cacheado por 60s por usuário.
 */
class UserAlertFeedService
{
    public function __construct(private readonly SyncStatusService $sync)
    {
    }

    private const SYNC_KEYS = ['office_sync', 'google_workspace_sync', 'cmdb_sync', 'veeam_sync', 'system_alert'];

    /**
     * @return array<int, array{titulo: string, descricao: string, severidade: string, url: ?string}>
     */
    public function paraUsuario(User $user): array
    {
        try {
            return Cache::remember('user-alerts:' . $user->id, now()->addSeconds(60), fn () => $this->montar($user));
        } catch (Throwable $e) {
            try {
                return $this->montar($user);
            } catch (Throwable $e2) {
                return [];
            }
        }
    }

    /**
     * @return array<int, array{titulo: string, descricao: string, severidade: string, url: ?string}>
     */
    private function montar(User $user): array
    {
        $assinados = $this->tiposQueRecebe($user);
        $alertas = [];

        // 1) Sincronizações com problema (erro/atraso/aviso) visíveis ao usuário.
        if (array_intersect($assinados, self::SYNC_KEYS) !== []) {
            try {
                foreach ($this->sync->fontes($user) as $fonte) {
                    $status = $fonte['status'] ?? '';
                    if (! in_array($status, [SyncStatusService::ERRO, SyncStatusService::ATRASADO, SyncStatusService::ATENCAO], true)) {
                        continue;
                    }
                    $critico = in_array($status, [SyncStatusService::ERRO, SyncStatusService::ATRASADO], true);
                    $alertas[] = [
                        'titulo' => (string) ($fonte['rotulo'] ?? 'Sincronização'),
                        'descricao' => (string) ($fonte['detalhe'] ?: ($fonte['rotulo_status'] ?? '')),
                        'severidade' => $critico ? 'critico' : 'aviso',
                        'url' => $fonte['rota'] ?? null,
                    ];
                }
            } catch (Throwable $e) {
                // Ignora: o sino não pode quebrar a página.
            }
        }

        // 2) Exclusão de contas do AD aguardando aprovação.
        if (in_array('ad_purge', $assinados, true) || $user->canAnyModule(['ad'])) {
            try {
                $pendentes = AdPurgeBatch::query()->where('status', AdPurgeBatch::STATUS_AGUARDANDO)->count();
                if ($pendentes > 0) {
                    $alertas[] = [
                        'titulo' => 'Exclusão de contas do AD',
                        'descricao' => $pendentes . ' lote(s) aguardando aprovação.',
                        'severidade' => 'aviso',
                        'url' => Route::has('ad.exclusao.index') ? route('ad.exclusao.index') : null,
                    ];
                }
            } catch (Throwable $e) {
                // Ignora.
            }
        }

        // 3) Requisições de peças ao CD já resolvidas (Isabeli fez a requisição):
        //    aviso para quem tem o painel da Bancada, com a data liberada para
        //    coleta. Fica em vigor até alguém marcar "Produtos Coletados".
        if ($user->canModule('bancada')) {
            try {
                $diasSemana = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
                $temRota = Route::has('bancada-servicos.cd-requisicoes.coletado');

                foreach (BancadaCdRequisicao::query()->aguardandoColeta()->orderBy('coleta_liberada_em')->get() as $req) {
                    $data = $req->coleta_liberada_em;
                    $quando = $data
                        ? ($diasSemana[(int) $data->dayOfWeek] . ' (' . $data->format('d/m') . ')')
                        : 'em breve';

                    $alertas[] = [
                        'titulo' => 'Peças solicitadas ao CD',
                        'descricao' => $req->total_itens . ' item(ns) — coleta liberada ' . $quando . '.',
                        'severidade' => 'aviso',
                        'url' => null,
                        'acao_label' => 'Produtos coletados',
                        'acao_url' => $temRota ? route('bancada-servicos.cd-requisicoes.coletado', $req) : null,
                    ];
                }
            } catch (Throwable $e) {
                // Ignora: o sino não pode quebrar a página.
            }
        }

        return $alertas;
    }

    /**
     * Tipos de aviso (event_key) em que o usuário está cadastrado.
     *
     * @return array<int, string>
     */
    private function tiposQueRecebe(User $user): array
    {
        try {
            $rec = CoreTiNotificationRecipient::query()
                ->where('email', $user->email)
                ->where('active', true)
                ->with(['rules' => fn ($q) => $q->where('active', true)])
                ->first();

            return $rec ? $rec->rules->pluck('event_key')->filter()->values()->all() : [];
        } catch (Throwable $e) {
            return [];
        }
    }
}
