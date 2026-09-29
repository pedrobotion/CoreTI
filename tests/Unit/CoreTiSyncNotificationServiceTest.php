<?php

namespace Tests\Unit;

use App\Mail\CoreTiNotificationMail;
use App\Models\CoreTiNotificationRecipient;
use App\Models\CoreTiNotificationRule;
use App\Services\CoreTiSyncNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Aviso de sincronização para a equipe.
 *
 * Os destinatários NÃO vêm mais do .env: eles são cadastrados na tela de
 * Configurações > Notificações, e o serviço só cai no config quando não existe
 * regra para o evento. Uma migration já cria as regras, então na prática a
 * tela é sempre a fonte — o caminho do config ficou como retaguarda.
 *
 * Este teste afirmava o contrato antigo e por isso falhava: encontrava a
 * regra "office_sync" ativa e sem ninguém cadastrado, e o serviço
 * corretamente não enviava nada.
 */
class CoreTiSyncNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function cadastrarDestinatario(string $eventKey, string $email): void
    {
        $regra = CoreTiNotificationRule::query()->firstOrCreate(
            ['event_key' => $eventKey],
            ['label' => $eventKey, 'active' => true]
        );

        $destinatario = CoreTiNotificationRecipient::query()->create([
            'name' => 'Equipe de teste',
            'email' => $email,
            'active' => true,
        ]);

        $regra->recipients()->attach($destinatario->id);
    }

    public function test_it_does_not_notify_a_successful_run_even_with_changes(): void
    {
        Mail::fake();
        config()->set('coreti.sync_notifications.enabled', true);
        $this->cadastrarDestinatario('office_sync', 'alerts@example.com');

        app(CoreTiSyncNotificationService::class)->notify('Office', ['created' => 10]);

        // Sucesso não avisa ninguém, mesmo com destinatário cadastrado: só
        // aviso e falha valem interrupção.
        Mail::assertNothingSent();
    }

    public function test_it_sends_a_warning_when_the_sync_has_actionable_changes(): void
    {
        Mail::fake();
        config()->set('coreti.sync_notifications.enabled', true);
        $this->cadastrarDestinatario('office_sync', 'alerts@example.com');

        app(CoreTiSyncNotificationService::class)->notify('Office', ['pending' => 2], 'warning');

        Mail::assertSent(CoreTiNotificationMail::class, function (CoreTiNotificationMail $mail): bool {
            return $mail->envelope()->subject === '[CoreTI] [AVISO] Sincronização Office concluída';
        });
    }

    public function test_regra_sem_destinatario_nao_envia_e_nao_cai_no_config(): void
    {
        /*
         | A regra existe e está ativa, mas ninguém foi cadastrado nela. Era
         | exatamente este o estado que derrubava o teste antigo — e o
         | comportamento está certo: sem destinatário, não há para quem
         | enviar, e cair no config silenciosamente mandaria o aviso para um
         | endereço que ninguém mantém.
         */
        Mail::fake();
        config()->set('coreti.sync_notifications.enabled', true);
        config()->set('coreti.sync_notifications.recipients', ['fallback@example.com']);

        CoreTiNotificationRule::query()->firstOrCreate(
            ['event_key' => 'office_sync'],
            ['label' => 'Office', 'active' => true]
        );

        app(CoreTiSyncNotificationService::class)->notify('Office', ['pending' => 2], 'warning');

        Mail::assertNothingSent();
    }
}
