<?php

namespace App\Mail;

use App\Services\CoreTiMailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CoreTiNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $data;

    public function __construct(array $data)
    {
        $this->data = app(CoreTiMailTemplateService::class)->prepare($data);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->data['assunto']);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.coreti.notification',
            text: 'emails.coreti.notification-text',
            with: $this->data,
        );
    }
}
