<?php

namespace App\Console\Commands;

use App\Models\CmdbDatabaseBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Detecta pares host+banco que pararam de reportar.
 *
 * Cobre a lacuna do fluxo do n8n: ele só reage ao evento que chega. Se o
 * agente morre, o cron é removido ou o servidor cai, nenhum evento chega e o
 * silêncio é interpretado como sucesso. Aqui o silêncio vira alerta.
 */
class VerificarBackupsAtrasados extends Command
{
    protected $signature = 'backups:verificar-atrasados
        {--horas= : Limite em horas sem reportar (padrão: config backups.stale_after_hours)}
        {--notificar : Registra o alerta no log da aplicação}';

    protected $description = '[Legado] Lista pares host/banco sem EVENTO de webhook no prazo. Use backups:verificar.';

    public function handle(): int
    {
        $this->warn('Comando legado: analisa apenas eventos recebidos por webhook (cmdb_database_backups).');
        $this->warn('A auditoria autônoma é backups:verificar, que consulta os servidores diretamente.');

        $horas = (int) ($this->option('horas') ?: config('backups.stale_after_hours', 36));
        $limite = now()->subHours($horas);

        $ultimos = CmdbDatabaseBackup::query()
            ->selectRaw('host, database_name, MAX(COALESCE(data_backup, received_at)) as ultimo_evento')
            ->groupBy('host', 'database_name')
            ->get();

        if ($ultimos->isEmpty()) {
            $this->info('Nenhum backup registrado ainda.');

            return self::SUCCESS;
        }

        $atrasados = $ultimos
            ->filter(fn ($linha) => $linha->ultimo_evento === null
                || \Illuminate\Support\Carbon::parse($linha->ultimo_evento)->lessThan($limite))
            ->values();

        $this->info(sprintf(
            '%d par(es) monitorado(s); %d sem reportar há mais de %dh.',
            $ultimos->count(),
            $atrasados->count(),
            $horas
        ));

        if ($atrasados->isEmpty()) {
            return self::SUCCESS;
        }

        $this->table(
            ['Host', 'Banco', 'Último evento', 'Horas em silêncio'],
            $atrasados->map(fn ($l) => [
                $l->host,
                $l->database_name,
                $l->ultimo_evento ?: 'nunca',
                $l->ultimo_evento
                    ? (int) \Illuminate\Support\Carbon::parse($l->ultimo_evento)->diffInHours(now())
                    : '-',
            ])->all()
        );

        if ($this->option('notificar')) {
            Log::warning('backup.atrasados', [
                'limite_horas' => $horas,
                'pares' => $atrasados->map(fn ($l) => $l->host.'/'.$l->database_name)->all(),
            ]);
        }

        // Código diferente de zero permite ao cron/monitoramento reagir.
        return self::FAILURE;
    }
}
