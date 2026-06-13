<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MasterTwoFactorLoginMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userName,
        public string $code,
        public int $validSeconds = 30,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Código de acceso Master — verificación en dos pasos',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.master-two-factor-login',
        );
    }
}
