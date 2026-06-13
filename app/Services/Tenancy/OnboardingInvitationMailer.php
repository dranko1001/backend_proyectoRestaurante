<?php

namespace App\Services\Tenancy;

use App\Mail\OnboardingInvitationMail;
use App\Mail\OnboardingSuccessMail;
use App\Models\Master\OnboardingInvitation;
use App\Models\Master\Tenant;
use App\Support\Tenancy\TenantUrl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OnboardingInvitationMailer
{
    public function isConfigured(): bool
    {
        $mailer = (string) config('mail.default', 'log');

        if (in_array($mailer, ['log', 'array'], true)) {
            return false;
        }

        if ($mailer === 'smtp') {
            return (string) config('mail.mailers.smtp.host') !== ''
                && (string) config('mail.mailers.smtp.username') !== '';
        }

        return true;
    }

    /**
     * @return array{sent: bool, error: ?string}
     */
    public function send(Tenant $tenant, OnboardingInvitation $invitation, string $plainToken): array
    {
        if (! $this->isConfigured()) {
            return [
                'sent' => false,
                'error' => 'SMTP no configurado (MAIL_MAILER=log o faltan credenciales).',
            ];
        }

        $onboardingUrl = TenantUrl::onboarding($plainToken);
        $expiresFormatted = $invitation->expires_at instanceof Carbon
            ? $invitation->expires_at->timezone(config('app.timezone'))->format('d/m/Y H:i')
            : (string) $invitation->expires_at;

        try {
            Mail::to($invitation->email)->send(new OnboardingInvitationMail(
                onboardingUrl: $onboardingUrl,
                slug: $tenant->slug,
                subdomainHost: $tenant->slug.'.'.TenantUrl::baseDomain(),
                expiresAtFormatted: $expiresFormatted,
            ));

            return ['sent' => true, 'error' => null];
        } catch (\Throwable $e) {
            Log::error('Onboarding invitation email failed', [
                'tenant_id' => $tenant->id,
                'email' => $invitation->email,
                'message' => $e->getMessage(),
            ]);

            return [
                'sent' => false,
                'error' => $this->friendlyError($e->getMessage()),
            ];
        }
    }

    /**
     * @param  array{
     *   admin_correo: string,
     *   nombre_comercial: string,
     *   tenant_url: string,
     *   admin_login: string,
     *   admin_productos_url: string,
     *   staff_url: string,
     *   cliente_url: string,
     * }  $summary
     * @return array{sent: bool, error: ?string}
     */
    public function sendActivationSummary(array $summary): array
    {
        if (! $this->isConfigured()) {
            return [
                'sent' => false,
                'error' => 'SMTP no configurado (MAIL_MAILER=log o faltan credenciales).',
            ];
        }

        try {
            Mail::to($summary['admin_correo'])->send(new OnboardingSuccessMail(
                nombreComercial: $summary['nombre_comercial'],
                tenantUrl: $summary['tenant_url'],
                adminCorreo: $summary['admin_correo'],
                adminLogin: $summary['admin_login'],
                adminProductosUrl: $summary['admin_productos_url'],
                staffUrl: $summary['staff_url'],
                clienteUrl: $summary['cliente_url'],
            ));

            return ['sent' => true, 'error' => null];
        } catch (\Throwable $e) {
            Log::error('Onboarding success summary email failed', [
                'email' => $summary['admin_correo'],
                'tenant_url' => $summary['tenant_url'],
                'message' => $e->getMessage(),
            ]);

            return [
                'sent' => false,
                'error' => $this->friendlyError($e->getMessage()),
            ];
        }
    }

    private function friendlyError(string $message): string
    {
        $lower = strtolower($message);

        if (
            str_contains($lower, 'could not be established')
            || str_contains($lower, 'unable to connect')
            || str_contains($lower, 'connection timed out')
            || str_contains($lower, 'no respondió')
            || str_contains($lower, 'stream_socket_client')
        ) {
            return 'Tu red o firewall bloquea la conexión SMTP (puertos 587/465). Copia el enlace de onboarding y envíalo por WhatsApp. En local puedes usar MAIL_MAILER=log en .env.';
        }

        if (
            str_contains($lower, 'authentication')
            || str_contains($lower, 'username and password')
            || str_contains($lower, '535')
        ) {
            return 'Credenciales SMTP incorrectas. En Gmail usa una contraseña de aplicación (no la contraseña normal).';
        }

        return 'No se pudo enviar el correo. Copia el enlace de onboarding y compártelo manualmente.';
    }
}
