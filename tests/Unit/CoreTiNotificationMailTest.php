<?php

namespace Tests\Unit;

use App\Enums\CoreTiMailType;
use App\Mail\CoreTiNotificationMail;
use App\Services\CoreTiMailTemplateService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CoreTiNotificationMailTest extends TestCase
{
    public static function messageTypes(): array
    {
        return array_map(fn (CoreTiMailType $type): array => [$type], CoreTiMailType::cases());
    }

    #[DataProvider('messageTypes')]
    public function test_subject_and_type_are_generated_for_all_message_types(CoreTiMailType $type): void
    {
        $mail = new CoreTiNotificationMail([
            'tipo_mensagem' => $type,
            'titulo' => 'Teste de comunicação',
        ]);

        self::assertSame('[CoreTI] [' . $type->value . '] Teste de comunicação', $mail->envelope()->subject);
        self::assertStringContainsString($type->value, $mail->render());
        self::assertStringContainsString($type->color(), $mail->render());
    }

    public function test_optional_fields_are_not_rendered_when_empty(): void
    {
        $data = app(CoreTiMailTemplateService::class)->prepare([
            'tipo_mensagem' => 'INFORMAÇÃO',
            'titulo' => 'Atualização',
            'destinatario' => 'Pedro',
            'mensagem_principal' => 'Mensagem de teste.',
            'url_acao' => 'javascript:alert(1)',
            'previsao' => '-',
        ]);

        $html = view('emails.coreti.notification', $data)->render();
        $text = view('emails.coreti.notification-text', $data)->render();

        self::assertStringNotContainsString('Previsão:', $html);
        self::assertStringNotContainsString('Previsão:', $text);
        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringNotContainsString('href=', $html);
    }

    public function test_plain_text_template_preserves_accents_and_optional_values(): void
    {
        $data = app(CoreTiMailTemplateService::class)->prepare([
            'tipo_mensagem' => 'MANUTENÇÃO',
            'titulo' => 'Manutenção programada',
            'destinatario' => 'João da Silva',
            'mensagem_principal' => 'A manutenção ocorrerá amanhã.',
            'servico' => 'CoreTI',
            'status' => 'Agendada',
            'orientacao' => 'Salve suas atividades.',
            'url_acao' => 'https://coreti.cocari.com.br',
            'texto_botao' => 'Acessar o CoreTI',
        ]);

        $text = view('emails.coreti.notification-text', $data)->render();

        self::assertStringContainsString('João da Silva', $text);
        self::assertStringContainsString('Manutenção programada', $text);
        self::assertStringContainsString('https://coreti.cocari.com.br', $text);
    }
}
