<?php

namespace Tests\Feature\Impressao;

use App\Models\Printer;
use App\Models\PrintJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Recebimento do log de impressão vindo dos servidores Windows.
 *
 * O ponto central é o reenvio ser inofensivo: o script pode repetir a mesma
 * janela por retomada, sobreposição ou conferência. Se repetir duplicasse, a
 * estatística ficaria inflada e ninguém rodaria de novo com segurança.
 */
class ColetaLogTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'token-de-teste-com-tamanho-suficiente-para-hash-equals';

    protected function setUp(): void
    {
        parent::setUp();

        config(['impressao.token' => self::TOKEN, 'impressao.allowed_ips' => []]);
    }

    /** @param  array<int, array<string, mixed>>  $eventos */
    private function postar(array $eventos, string $servidor = 'IMP01', ?string $token = self::TOKEN)
    {
        return $this->withHeaders($token === null ? [] : ['X-Coreti-Impressao-Token' => $token])
            ->postJson(route('api.impressao.lote'), ['servidor' => $servidor, 'eventos' => $eventos]);
    }

    /** @return array<string, mixed> */
    private function evento(array $dados = []): array
    {
        return array_merge([
            'impresso_em' => '2026-09-22T07:26:58-03:00',
            'usuario' => 'adailton.aragao',
            'estacao' => 'FIA-SEG01',
            'impressora' => 'FIA-LASER-EXP01',
            'porta' => '172.19.2.209',
            'doc_numero' => '546',
            'paginas' => 2,
            'tamanho_bytes' => 201625,
        ], $dados);
    }

    public function test_sem_token_o_envio_e_recusado(): void
    {
        $this->postar([$this->evento()], token: null)->assertUnauthorized();
        $this->assertSame(0, PrintJob::count());
    }

    public function test_token_errado_e_recusado(): void
    {
        $this->postar([$this->evento()], token: 'errado')->assertUnauthorized();
    }

    public function test_sem_token_configurado_o_servico_falha_fechado(): void
    {
        // Endpoint que escreve no banco não pode liberar por falta de config.
        config(['impressao.token' => '']);

        $this->postar([$this->evento()])->assertStatus(503);
    }

    public function test_ip_fora_da_lista_e_recusado(): void
    {
        config(['impressao.allowed_ips' => ['172.17.3.46']]);

        $this->postar([$this->evento()])->assertForbidden();
    }

    public function test_grava_o_evento(): void
    {
        $this->postar([$this->evento()])->assertOk()->assertJson(['gravados' => 1, 'repetidos' => 0]);

        $job = PrintJob::firstOrFail();
        $this->assertSame('IMP01', $job->servidor);
        $this->assertSame('adailton.aragao', $job->usuario);
        $this->assertSame('FIA-LASER-EXP01', $job->impressora);
        $this->assertSame(2, $job->paginas);
    }

    public function test_reenviar_a_mesma_janela_nao_duplica(): void
    {
        $eventos = [$this->evento(), $this->evento(['doc_numero' => '550', 'paginas' => 3])];

        $this->postar($eventos)->assertOk()->assertJson(['gravados' => 2]);
        $this->postar($eventos)->assertOk()->assertJson(['gravados' => 0, 'repetidos' => 2]);

        $this->assertSame(2, PrintJob::count());
    }

    public function test_repetido_dentro_do_proprio_lote_nao_derruba_o_envio(): void
    {
        /*
         | insertOrIgnore não protege contra chave duplicada na MESMA
         | inserção — o banco recusaria o lote inteiro e o servidor perderia
         | os eventos bons junto com o repetido.
         */
        $this->postar([$this->evento(), $this->evento(), $this->evento(['doc_numero' => '999'])])
            ->assertOk()
            ->assertJson(['gravados' => 2, 'repetidos' => 1]);

        $this->assertSame(2, PrintJob::count());
    }

    public function test_o_mesmo_numero_de_trabalho_em_servidores_diferentes_sao_eventos_distintos(): void
    {
        // O número do trabalho recicla dentro de cada fila; sozinho não é identidade.
        $this->postar([$this->evento()], 'IMP01')->assertOk();
        $this->postar([$this->evento()], 'IMP02')->assertOk();

        $this->assertSame(2, PrintJob::count());
    }

    public function test_horario_local_do_windows_vira_utc(): void
    {
        /*
         | O Windows manda o horário de Brasília com offset. Sem converter, o
         | gráfico "por horário" sairia três horas deslocado — justamente a
         | pergunta que ele existe para responder.
         */
        $this->postar([$this->evento(['impresso_em' => '2026-09-22T07:26:58-03:00'])])->assertOk();

        $this->assertSame('2026-09-22 10:26:58', PrintJob::firstOrFail()->impresso_em->toDateTimeString());
    }

    public function test_trabalho_com_zero_pagina_e_guardado(): void
    {
        // Cancelamento na fila é desperdício que vale medir, não lixo.
        $this->postar([$this->evento(['paginas' => 0])])->assertOk()->assertJson(['gravados' => 1]);

        $this->assertSame(1, PrintJob::semPagina()->count());
    }

    public function test_evento_sem_usuario_ou_impressora_e_ignorado_sem_derrubar_o_lote(): void
    {
        $resposta = $this->postar([
            $this->evento(),
            $this->evento(['usuario' => '', 'doc_numero' => '777']),
        ]);

        // A validação recusa o lote todo quando o campo é obrigatório.
        $resposta->assertStatus(422);
    }

    public function test_vincula_a_impressora_monitorada_quando_o_nome_casa(): void
    {
        $printer = Printer::create([
            'insight_device_id' => 1, 'insight_customer_id' => 1163,
            'serial_number' => 'ABC', 'hostname' => 'FIA-LASER-EXP01',
        ]);

        $this->postar([$this->evento()])->assertOk();

        $this->assertSame($printer->id, PrintJob::firstOrFail()->printer_id);
    }

    public function test_impressora_fora_do_portal_fica_sem_vinculo(): void
    {
        // 22 das 61 do relatório não estão no Insight; nulo é informação.
        $this->postar([$this->evento(['impressora' => 'ZEBRA-EC02'])])->assertOk();

        $this->assertNull(PrintJob::firstOrFail()->printer_id);
    }

    public function test_o_script_pergunta_ate_onde_ja_enviou(): void
    {
        $this->postar([$this->evento()])->assertOk();

        $this->withHeaders(['X-Coreti-Impressao-Token' => self::TOKEN])
            ->getJson(route('api.impressao.ultimo-evento', ['servidor' => 'IMP01']))
            ->assertOk()
            ->assertJson(['servidor' => 'IMP01', 'total' => 1, 'dias_padrao' => 7]);
    }

    public function test_lote_acima_do_teto_e_recusado(): void
    {
        config(['impressao.max_por_lote' => 2]);

        $this->postar([$this->evento(), $this->evento(['doc_numero' => '2']), $this->evento(['doc_numero' => '3'])])
            ->assertStatus(422);
    }
}
