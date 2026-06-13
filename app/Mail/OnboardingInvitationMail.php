<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OnboardingInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $onboardingUrl,
        public string $slug,
        public string $subdomainHost,
        public string $expiresAtFormatted,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Configura tu restaurante — enlace de activación',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.onboarding-invitation',
        );
    }
}
