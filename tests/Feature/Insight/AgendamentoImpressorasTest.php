<?php

namespace Tests\Feature\Insight;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * O agendamento das impressoras roda em horário de Brasília.
 *
 * O app roda em UTC e o agendador do Laravel segue app.timezone. Sem declarar
 * o fuso em cada tarefa, "00:00" viraria 21:00 de Brasília — três horas de
 * diferença que ninguém percebe olhando o código, só olhando o log no dia
 * seguinte.
 */
class AgendamentoImpressorasTest extends TestCase
{
    /** @return array<int, \Illuminate\Console\Scheduling\Event> */
    private function eventos(): array
    {
        return array_values(array_filter(
            app(Schedule::class)->events(),
            fn ($e): bool => str_contains((string) $e->command, 'impressoras:sincronizar')
        ));
    }

    public function test_sao_quatro_execucoes_por_dia(): void
    {
        $this->assertCount(4, $this->eventos());
    }

    public function test_nos_horarios_combinados_com_a_ti(): void
    {
        $expressoes = array_map(fn ($e): string => $e->expression, $this->eventos());
        sort($expressoes);

        $this->assertSame(['0 0 * * *', '0 19 * * *', '0 7 * * *', '30 12 * * *'], $expressoes);
    }

    public function test_todas_declaram_o_fuso_de_brasilia(): void
    {
        foreach ($this->eventos() as $evento) {
            $this->assertSame(
                'America/Sao_Paulo',
                $evento->timezone,
                "a tarefa {$evento->expression} rodaria em UTC, 3h fora do horário combinado"
            );
        }
    }

    public function test_uma_execucao_nao_se_sobrepoe_a_outra(): void
    {
        // Execução lenta cruzando com a seguinte dobraria o consumo da API.
        foreach ($this->eventos() as $evento) {
            $this->assertTrue($evento->withoutOverlapping, "{$evento->expression} pode se sobrepor");
        }
    }

    public function test_o_teto_diario_cobre_o_agendamento_com_folga(): void
    {
        /*
         | 4 execuções × 4 chamadas + 1 autenticação = 17 por dia. Se alguém
         | apertar o teto abaixo disso, o próprio agendamento passa a falhar
         | no fim do dia — e é melhor descobrir aqui que em produção.
         */
        $custoDiario = (4 * 4) + 1;

        $this->assertGreaterThan(
            $custoDiario,
            (int) config('insight.limites.por_dia'),
            'o teto diário não cobre nem o que está agendado'
        );
    }
}
