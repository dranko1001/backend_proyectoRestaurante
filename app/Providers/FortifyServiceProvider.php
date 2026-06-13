<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Support\Tenancy\TenantUrl;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Laravel\Fortify\Fortify;

/**
 * Fortify se usa solo para: reset de contraseña, acciones TOTP (2FA) y rate limiters.
 * El login admin vive en AdminAuthController (API + Sanctum).
 */
class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Fortify::ignoreRoutes();

        $this->app->singleton(ResetsUserPasswords::class, ResetUserPassword::class);
    }

    public function boot(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        ResetPassword::createUrlUsing(function ($user, string $token) {
            $tenant = app()->bound('tenant.current') ? app('tenant.current') : null;
            $base = $tenant
                ? TenantUrl::appForSlug($tenant->slug)
                : TenantUrl::frontendOrigin();

            return $base.'/restablecer-contrasena?token='.urlencode($token)
                .'&correo='.urlencode($user->getEmailForPasswordReset());
        });

        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $tenant = app()->bound('tenant.current') ? app('tenant.current') : null;
            $base = $tenant
                ? TenantUrl::appForSlug($tenant->slug)
                : TenantUrl::frontendOrigin();

            $url = $base.'/restablecer-contrasena?token='.urlencode($token)
                .'&correo='.urlencode($notifiable->getEmailForPasswordReset());

            $minutes = (int) config('auth.passwords.usuarios.expire', 60);

            return (new MailMessage)
                ->subject('Restablece tu contraseña de administrador')
                ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta de administrador.')
                ->action('Crear nueva contraseña', $url)
                ->line("Este enlace caduca en {$minutes} minutos.")
                ->line('Si no solicitaste esto, ignora este correo.');
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(
                Str::lower((string) $request->input(Fortify::username())).'|'.$request->ip()
            );

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            $throttleKey = Str::transliterate(
                Str::lower((string) $request->input('challenge_token', '')).'|'.$request->ip()
            );

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
