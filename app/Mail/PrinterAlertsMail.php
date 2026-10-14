<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail diário dos alertas de hardware das impressoras, com um cartão por
 * equipamento: nome, série, unidade/local, IP e o erro já explicado em
 * português (ver App\Support\Impressao\CatalogoErrosImpressora).
 */
class PrinterAlertsMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int,array<string,mixed>>  $alertas
     */
    public function __construct(
        public array $alertas,
        public int $total,
        public int $criticos,
        public ?string $urlAcao = null,
        public ?string $regiao = null,
    ) {}

    public function envelope(): Envelope
    {
        $plural = $this->total === 1 ? 'alerta' : 'alertas';
        $regiao = $this->regiao ? " ({$this->regiao})" : '';

        return new Envelope(
            subject: "[CoreTI] [INCIDENTE] Alertas de impressoras{$regiao} — {$this->total} {$plural}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.impressoras.alertas',
            with: [
                'alertas' => $this->alertas,
                'total' => $this->total,
                'criticos' => $this->criticos,
                'regiao' => $this->regiao,
                'url_acao' => $this->urlAcao,
                'data_hora' => now()->format('d/m/Y H:i'),
                'logo_url' => filter_var((string) config('coreti.mail_logo_url'), FILTER_VALIDATE_URL) ?: null,
            ],
        );
    }
}
