<?php

namespace App\Services\Auth;

use App\Mail\AdminPasswordResetMail;
use App\Models\Usuario;
use App\Support\Tenancy\TenantUrl;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

class AdminPasswordResetMailer
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
     * @return array{
     *   status: string,
     *   email_sent: bool,
     *   reset_url: ?string,
     *   error: ?string,
     *   is_admin: bool
     * }
     */
    public function sendForAdmin(string $correo): array
    {
        $usuario = Usuario::query()
            ->with('cargo')
            ->where('correo', $correo)
            ->where('activo', true)
            ->first();

        if (! $usuario || $usuario->cargo?->nombre !== 'ADMINISTRADOR') {
            return [
                'status' => 'generic',
                'email_sent' => false,
                'reset_url' => null,
                'error' => null,
                'is_admin' => false,
            ];
        }

        $resetUrl = null;
        $emailSent = false;
        $error = null;

        $status = Password::broker('usuarios')->sendResetLink(
            ['correo' => $correo],
            function (Usuario $user, string $token) use (&$resetUrl, &$emailSent, &$error) {
                $resetUrl = $this->buildResetUrl($user, $token);

                if (! $this->isConfigured()) {
                    $error = 'SMTP no configurado (MAIL_MAILER=log).';

                    return Password::RESET_LINK_SENT;
                }

                try {
                    $tenant = app()->bound('tenant.current') ? app('tenant.current') : null;
                    $nombreComercial = $tenant?->nombre_comercial ?? $tenant?->slug ?? '';

                    Mail::to($user->correo)->send(new AdminPasswordResetMail(
                        resetUrl: $resetUrl,
                        nombreComercial: (string) $nombreComercial,
                        expireMinutes: (int) config('auth.passwords.usuarios.expire', 60),
                    ));
                    $emailSent = true;
                } catch (\Throwable $e) {
                    Log::error('Admin password reset email failed', [
                        'correo' => $user->correo,
                        'message' => $e->getMessage(),
                    ]);
                    $error = $this->friendlyError($e->getMessage());
                }

                return Password::RESET_LINK_SENT;
            }
        );

        return [
            'status' => $status,
            'email_sent' => $emailSent,
            'reset_url' => $resetUrl,
            'error' => $error,
            'is_admin' => true,
        ];
    }

    public function buildResetUrl(Usuario $user, string $token): string
    {
        $tenant = app()->bound('tenant.current') ? app('tenant.current') : null;
        $base = $tenant
            ? TenantUrl::appForSlug($tenant->slug)
            : TenantUrl::frontendOrigin();

        return $base.'/restablecer-contrasena?token='.urlencode($token)
            .'&correo='.urlencode($user->getEmailForPasswordReset());
    }

    private function friendlyError(string $message): string
    {
        $lower = strtolower($message);

        if (
            str_contains($lower, 'could not be established')
            || str_contains($lower, 'unable to connect')
            || str_contains($lower, 'stream_socket_client')
        ) {
            return 'No hay conexión al servidor de correo. En local puedes copiar el enlace de recuperación.';
        }

        return 'No se pudo enviar el correo de recuperación.';
    }
}
