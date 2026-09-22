<?php

namespace Tests\Unit;

use App\Mail\CoreTiNotificationMail;
use App\Services\CoreTiSyncNotificationService;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CoreTiSyncNotificationServiceTest extends TestCase
{
    public function test_it_does_not_notify_a_successful_run_even_with_changes(): void
    {
        Mail::fake();
        config()->set('coreti.sync_notifications.enabled', true);
        config()->set('coreti.sync_notifications.recipients', ['alerts@example.com']);
        app(CoreTiSyncNotificationService::class)->notify('Teste', ['created' => 10]);

        Mail::assertNothingSent();
    }

    public function test_it_sends_a_warning_when_the_sync_has_actionable_changes(): void
    {
        Mail::fake();
        config()->set('coreti.sync_notifications.enabled', true);
        config()->set('coreti.sync_notifications.recipients', ['alerts@example.com']);

        app(CoreTiSyncNotificationService::class)->notify('Office', ['pending' => 2], 'warning');

        Mail::assertSent(CoreTiNotificationMail::class, function (CoreTiNotificationMail $mail): bool {
            return $mail->envelope()->subject === '[CoreTI] [AVISO] Sincronização Office concluída';
        });
    }
}
