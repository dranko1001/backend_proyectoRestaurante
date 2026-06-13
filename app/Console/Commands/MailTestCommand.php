<?php

namespace App\Console\Commands;

use App\Mail\OnboardingInvitationMail;
use App\Support\Tenancy\TenantUrl;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class MailTestCommand extends Command
{
    protected $signature = 'mail:test {email : Correo de destino}';

    protected $description = 'Envía un correo de prueba (plantilla de invitación onboarding)';

    public function handle(): int
    {
        $to = $this->argument('email');

        try {
            Mail::to($to)->send(new OnboardingInvitationMail(
                onboardingUrl: TenantUrl::onboarding('ejemplo-prueba'),
                slug: 'ejemplo',
                subdomainHost: 'ejemplo.'.TenantUrl::baseDomain(),
                expiresAtFormatted: now()->addDays(3)->format('d/m/Y H:i'),
            ));
            $this->info("Correo de prueba enviado a {$to}.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Error al enviar: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
