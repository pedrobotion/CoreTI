<?php

namespace App\Services\Impressao;

use App\Models\Printer;
use App\Models\PrintQueueDiscovery;
use Illuminate\Support\Collection;

/**
 * Concilia o que os servidores de impressão enxergam com a base da Simpress.
 *
 * O caminho é: o servidor informa suas filas (nome + IP da porta), o CoreTI lê
 * o serial de cada IP por SNMP, e o serial casa com a impressora da Simpress.
 *
 * O serial é a chave certa porque não muda: o IP muda de DHCP, o nome da fila
 * é escolha de quem cadastrou, mas o serial é do equipamento. É por isso que
 * ele revela o erro da Simpress em vez de herdá-lo — 112 impressoras estão lá
 * com IP 127.0.0.1 ou trocado.
 */
class PrinterFleetReconciliationService
{
    public function __construct(private readonly SnmpReader $snmp) {}

    /**
     * Registra as filas que um servidor reportou.
     *
     * Não lê SNMP aqui: a leitura é uma etapa à parte (consultarPendentes),
     * para o recebimento ser rápido e a consulta de rede, que é lenta, não
     * segurar a resposta ao servidor.
     *
     * @param  array<int, array<string, mixed>>  $filas
     * @return array{recebidas:int, atualizadas:int}
     */
    public function registrarFilas(string $servidor, array $filas): array
    {
        $servidor = mb_strtoupper(trim($servidor));
        $recebidas = 0;
        $novas = 0;
        $ignoradas = 0;

        foreach ($filas as $fila) {
            $nome = trim((string) ($fila['fila'] ?? ''));
            if ($nome === '') {
                continue;
            }

            // Térmica/etiqueta não é da Simpress: não entra na conciliação.
            if ($this->foraDaConciliacao($fila['driver'] ?? null)) {
                $ignoradas++;

                continue;
            }

            $ip = $this->ipValido($fila['ip_porta'] ?? null);

            $registro = PrintQueueDiscovery::firstOrNew([
                'servidor' => $servidor,
                'fila' => $nome,
            ]);

            // IP mudou: o serial precisa ser relido, então zera a consulta.
            if ($registro->exists && $registro->ip_porta !== $ip) {
                $registro->snmp_status = PrintQueueDiscovery::SNMP_NAO_CONSULTADO;
                $registro->serial_number = null;
            }

            $novas += $registro->exists ? 0 : 1;

            $registro->fill([
                'ip_porta' => $ip,
                'driver' => trim((string) ($fila['driver'] ?? '')) ?: null,
                'visto_em' => now(),
            ])->save();

            $recebidas++;
        }

        return ['recebidas' => $recebidas, 'atualizadas' => $recebidas - $novas, 'ignoradas' => $ignoradas];
    }

    /**
     * A fila é de equipamento fora do contrato da Simpress?
     *
     * Decide pelo DRIVER, não pelo nome: o driver identifica o equipamento de
     * verdade (Bematech, Zebra), enquanto o nome TERM/BAL é convenção e deixa
     * escapar casos — havia Bematech sem "TERM" no nome e Zebra sem "BAL".
     */
    private function foraDaConciliacao(mixed $driver): bool
    {
        $driver = mb_strtolower(trim((string) $driver));

        if ($driver === '') {
            return false;
        }

        foreach ((array) config('impressao.drivers_fora_da_conciliacao', []) as $padrao) {
            if ($padrao !== '' && str_contains($driver, mb_strtolower($padrao))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove filas já registradas cujo driver entrou na lista de ignorados.
     *
     * Serve para quando a regra muda: filas gravadas antes do filtro precisam
     * sair do painel.
     */
    public function limparForaDaConciliacao(): int
    {
        $removidas = 0;

        foreach (PrintQueueDiscovery::query()->whereNotNull('driver')->get() as $registro) {
            if ($this->foraDaConciliacao($registro->driver)) {
                $registro->delete();
                $removidas++;
            }
        }

        return $removidas;
    }

    /**
     * Lê por SNMP as filas ainda não consultadas e casa com a Simpress.
     *
     * @return array{consultadas:int, com_serial:int, casadas:int, divergentes:int, sem_resposta:int}
     */
    public function consultarPendentes(?int $limite = null, bool $tudo = false): array
    {
        $query = ($tudo ? PrintQueueDiscovery::paraReconsultarTudo() : PrintQueueDiscovery::aConsultar())
            ->orderBy('id');
        if ($limite !== null) {
            $query->limit($limite);
        }

        $resumo = ['consultadas' => 0, 'com_serial' => 0, 'casadas' => 0, 'divergentes' => 0, 'sem_resposta' => 0];

        foreach ($query->get() as $registro) {
            $resumo['consultadas']++;
            $this->consultar($registro, $resumo);
        }

        return $resumo;
    }

    /** Relê uma fila específica, ignorando o status anterior. */
    public function reconsultar(PrintQueueDiscovery $registro): PrintQueueDiscovery
    {
        $resumo = [];
        $this->consultar($registro, $resumo);

        return $registro->fresh('printer');
    }

    /** @param  array<string, int>  $resumo */
    private function consultar(PrintQueueDiscovery $registro, array &$resumo): void
    {
        $registro->snmp_verificado_em = now();

        if ($registro->ip_porta === null) {
            $registro->snmp_status = PrintQueueDiscovery::SNMP_SEM_RESPOSTA;
            $registro->snmp_detalhe = 'Fila sem IP na porta.';
            $registro->save();

            return;
        }

        $serial = $this->snmp->serial($registro->ip_porta);

        if ($serial === null) {
            $registro->snmp_status = PrintQueueDiscovery::SNMP_SEM_RESPOSTA;
            $registro->snmp_detalhe = 'Sem resposta SNMP no IP '.$registro->ip_porta.'.';
            $registro->save();
            $resumo['sem_resposta'] = ($resumo['sem_resposta'] ?? 0) + 1;

            return;
        }

        $resumo['com_serial'] = ($resumo['com_serial'] ?? 0) + 1;

        $registro->serial_number = $serial;
        $registro->modelo_snmp = $this->snmp->get($registro->ip_porta, SnmpReader::OID_MODELO);

        $printer = Printer::query()->where('serial_number', $serial)->first();

        if ($printer === null) {
            $registro->snmp_status = PrintQueueDiscovery::SNMP_OK;
            $registro->snmp_detalhe = 'Serial lido, mas sem correspondência na Simpress.';
            $registro->printer_id = null;
            $registro->ip_divergente = false;
            $registro->ip_simpress = null;
            $registro->save();

            return;
        }

        // Casou: registra o vínculo e compara o IP.
        $resumo['casadas'] = ($resumo['casadas'] ?? 0) + 1;

        $ipSimpress = trim((string) $printer->ip_address);
        $divergente = $ipSimpress !== $registro->ip_porta;

        if ($divergente) {
            $resumo['divergentes'] = ($resumo['divergentes'] ?? 0) + 1;
        }

        $registro->fill([
            'snmp_status' => PrintQueueDiscovery::SNMP_OK,
            'snmp_detalhe' => $divergente
                ? 'IP na Simpress ('.($ipSimpress ?: 'vazio').') difere do IP real.'
                : 'Casado, IP confere.',
            'printer_id' => $printer->id,
            'ip_divergente' => $divergente,
            'ip_simpress' => $ipSimpress ?: null,
        ])->save();
    }

    /** Descarta IP inválido (loopback, vazio, texto). */
    private function ipValido(mixed $valor): ?string
    {
        $ip = trim((string) $valor);

        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        // 127.x é a própria máquina, não a impressora: inútil para SNMP.
        if (str_starts_with($ip, '127.')) {
            return null;
        }

        return $ip;
    }

    /** @return Collection<int, PrintQueueDiscovery> */
    public function divergenciasDeIp(): Collection
    {
        return PrintQueueDiscovery::comDivergenciaDeIp()->with('printer')->orderBy('servidor')->orderBy('fila')->get();
    }
}
