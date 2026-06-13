<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OnboardingSuccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $nombreComercial,
        public string $tenantUrl,
        public string $adminCorreo,
        public string $adminLogin,
        public string $adminProductosUrl,
        public string $staffUrl,
        public string $clienteUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '¡Tu restaurante está listo! — '.$this->nombreComercial,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.onboarding-success',
        );
    }
}
