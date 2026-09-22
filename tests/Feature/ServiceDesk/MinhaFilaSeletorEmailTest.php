<?php

namespace Tests\Feature\ServiceDesk;

use App\Models\JiraIssue;
use App\Models\ServiceDeskQueueEmail;
use App\Models\User;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Seletor de e-mail da Minha fila.
 *
 * As opções vêm do histórico de chamados: oferecer um endereço que nunca
 * aparece como relator só produziria fila vazia.
 */
class MinhaFilaSeletorEmailTest extends TestCase
{
    use RefreshDatabase;

    private function operador(): User
    {
        $user = User::factory()->create(['role' => 'user', 'is_active' => true]);
        app(ModuleAccessService::class)->sync($user, ['servicedesk' => ['view']]);

        return $user->fresh();
    }

    private function chamado(string $relatorEmail, array $override = []): JiraIssue
    {
        static $n = 0;
        $n++;

        return JiraIssue::create(array_merge([
            'chave' => 'TIC-'.$n,
            'resumo' => 'Chamado de teste '.$n,
            'status' => 'Concluído',
            'relator_email' => $relatorEmail,
            'relator_nome' => 'Solicitante '.$n,
        ], $override));
    }

    public function test_opcoes_vem_dos_relatores_dos_chamados(): void
    {
        $this->chamado('ana@cocari.com.br');
        $this->chamado('ana@cocari.com.br');
        $this->chamado('bruno@cocari.com.br');

        $resposta = $this->actingAs($this->operador())->get(route('service-desk.my-queue'));

        $resposta->assertOk();
        $resposta->assertSee('ana@cocari.com.br');
        $resposta->assertSee('bruno@cocari.com.br');
    }

    /** Ordenado por volume: quem mais registra aparece primeiro. */
    public function test_opcoes_ordenadas_por_volume(): void
    {
        $this->chamado('pouco@cocari.com.br');
        foreach (range(1, 3) as $i) {
            $this->chamado('muito@cocari.com.br');
        }

        $resposta = $this->actingAs($this->operador())->get(route('service-desk.my-queue'));

        $resposta->assertSeeInOrder(['muito@cocari.com.br', 'pouco@cocari.com.br']);
    }

    /** E-mail já cadastrado não pode reaparecer como opção. */
    public function test_email_ja_na_fila_sai_da_lista(): void
    {
        $this->chamado('ja.tenho@cocari.com.br');
        $this->chamado('ainda.nao@cocari.com.br');

        $user = $this->operador();
        ServiceDeskQueueEmail::create(['user_id' => $user->id, 'email' => 'ja.tenho@cocari.com.br']);

        $html = $this->actingAs($user)->get(route('service-desk.my-queue'))->getContent();

        preg_match_all('/<option value="([^"]*@[^"]*)"/', $html, $m);
        $opcoes = $m[1];

        $this->assertContains('ainda.nao@cocari.com.br', $opcoes);
        $this->assertNotContains('ja.tenho@cocari.com.br', $opcoes);
    }

    public function test_cadastro_pelo_seletor_funciona(): void
    {
        $this->chamado('escolhido@cocari.com.br');
        $user = $this->operador();

        $this->actingAs($user)
            ->post(route('service-desk.my-queue.emails.store'), ['email' => 'escolhido@cocari.com.br'])
            ->assertRedirect();

        $this->assertDatabaseHas('service_desk_queue_emails', [
            'user_id' => $user->id,
            'email' => 'escolhido@cocari.com.br',
        ]);
    }

    /** O campo livre continua valendo para quem ainda não abriu chamado. */
    public function test_email_digitado_manualmente_continua_aceito(): void
    {
        $user = $this->operador();

        $this->actingAs($user)
            ->post(route('service-desk.my-queue.emails.store'), ['email' => 'NOVO@Cocari.com.br'])
            ->assertRedirect();

        $this->assertDatabaseHas('service_desk_queue_emails', [
            'user_id' => $user->id,
            'email' => 'novo@cocari.com.br',
        ]);
    }

    public function test_sem_chamados_a_tela_abre_em_modo_digitacao(): void
    {
        $resposta = $this->actingAs($this->operador())->get(route('service-desk.my-queue'));

        $resposta->assertOk();
        // Sem opções, o formulário já nasce com o campo de digitação ativo.
        $resposta->assertSee("digitando: true", false);
    }

    public function test_email_invalido_e_recusado(): void
    {
        $this->actingAs($this->operador())
            ->post(route('service-desk.my-queue.emails.store'), ['email' => 'nao-e-email'])
            ->assertSessionHasErrors('email');
    }
}
