<?php

namespace App\Services\Insight;

use App\Models\Printer;
use App\Models\PrinterAlert;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Sincroniza os alertas atuais das impressoras (prtAlert) a partir da API do
 * EKM/Insight. Os alertas só existem por dispositivo (/alerts/current), então a
 * varredura é uma chamada por impressora — limitada pelo teto diário da API
 * (config/insight.php). Por isso varre só as monitoradas e com contato recente.
 *
 * Mantém o histórico: um alerta que some da resposta da impressora é marcado
 * como resolvido, em vez de apagado.
 */
class PrinterAlertSyncService
{
    public function __construct(private readonly InsightApiClient $api) {}

    /**
     * @return array{impressoras:int,consultadas:int,alertas_abertos:int,novos:int,resolvidos:int,erros:int,interrompido:bool}
     */
    public function sincronizar(?int $limite = null, bool $dryRun = false): array
    {
        $query = Printer::query()
            ->whereNotNull('insight_device_id')
            ->where('monitor_status', 'Y')
            ->where('last_contact', '>=', now()->subHours(24))
            ->orderBy('id');

        if ($limite !== null) {
            $query->limit($limite);
        }

        $impressoras = $query->get(['id', 'insight_device_id', 'serial_number', 'manufacturer', 'model']);

        $consultadas = 0;
        $novos = 0;
        $resolvidos = 0;
        $erros = 0;
        $interrompido = false;

        foreach ($impressoras as $printer) {
            try {
                $alertas = $this->api->alertasAtuais((int) $printer->insight_device_id);
            } catch (Throwable $e) {
                // Teto diário estourado interrompe o laço (não adianta insistir).
                if (str_contains($e->getMessage(), 'teto') || str_contains(mb_strtolower($e->getMessage()), 'limite')) {
                    $interrompido = true;
                    break;
                }
                $erros++;

                continue;
            }

            $consultadas++;

            if ($dryRun) {
                $novos += count($alertas);

                continue;
            }

            $chavesAtuais = [];

            foreach ($alertas as $a) {
                try {
                    $detectado = isset($a['date']) ? Carbon::parse($a['date']) : now();
                    // mib_code é unsigned; a API manda -1 em alertas AVAILABILITY.
                    $mib = max(0, (int) ($a['mibCode'] ?? 0));
                    $chave = [
                        'printer_id' => $printer->id,
                        'alert_class' => (string) ($a['alertClass'] ?? ''),
                        'mib_code' => $mib,
                        'detectado_em' => $detectado,
                    ];
                    $chavesAtuais[] = $chave['alert_class'].'|'.$mib.'|'.$detectado->toDateTimeString();

                    $alerta = PrinterAlert::query()->firstOrNew($chave);
                    $eraNovo = ! $alerta->exists;
                    $alerta->fill([
                        'resolvido_em' => null,
                        'engine_cycles' => max(0, (int) ($a['engineCycles'] ?? 0)),
                        'training_level' => max(0, (int) ($a['trainingLevel'] ?? 0)),
                        'severity_level' => max(0, (int) ($a['severityLevel'] ?? 0)),
                        'descricao' => mb_substr((string) ($a['description'] ?? ''), 0, 255),
                        'raw_data' => $a,
                    ])->save();

                    if ($eraNovo) {
                        $novos++;
                    }
                } catch (Throwable $e) {
                    $erros++;
                }
            }

            // Alertas antes abertos que não vieram agora = resolvidos.
            $abertos = PrinterAlert::query()->where('printer_id', $printer->id)->whereNull('resolvido_em')->get();
            foreach ($abertos as $ab) {
                $k = $ab->alert_class.'|'.$ab->mib_code.'|'.Carbon::parse($ab->detectado_em)->toDateTimeString();
                if (! in_array($k, $chavesAtuais, true)) {
                    $ab->update(['resolvido_em' => now()]);
                    $resolvidos++;
                }
            }
        }

        $alertasAbertos = $dryRun ? $novos : (int) PrinterAlert::query()->whereNull('resolvido_em')->count();

        return [
            'impressoras' => $impressoras->count(),
            'consultadas' => $consultadas,
            'alertas_abertos' => $alertasAbertos,
            'novos' => $novos,
            'resolvidos' => $resolvidos,
            'erros' => $erros,
            'interrompido' => $interrompido,
        ];
    }
}
