<?php

namespace App\Providers;

use App\Models\Pedido;
use App\Models\Reserva;
use App\Models\Venta;
use App\Policies\PedidoPolicy;
use App\Policies\ReservaPolicy;
use App\Policies\VentaPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureSslCaBundle();
        $this->configureRateLimiting();

        // Autorización a nivel de recurso (dueño), reforzando la protección por rol.
        Gate::policy(Pedido::class, PedidoPolicy::class);
        Gate::policy(Venta::class, VentaPolicy::class);
        Gate::policy(Reserva::class, ReservaPolicy::class);

        Lang::addLines(
            [
                'auth.failed' => 'Credenciales inválidas.',
                'passwords.sent' => 'Si el correo pertenece a un administrador activo, recibirás un enlace para restablecer la contraseña.',
                'passwords.reset' => 'Contraseña actualizada correctamente.',
                'passwords.token' => 'El enlace de recuperación no es válido o ya expiró.',
                'passwords.user' => 'No encontramos un administrador con ese correo.',
                'passwords.throttled' => 'Espera un momento antes de volver a solicitar el enlace.',
                'validation.uploaded' => 'No se pudo subir la imagen. Puede ser demasiado pesada para el servidor; prueba con un archivo más liviano o continúa sin logo.',
                'validation.max.file' => 'La imagen es demasiado pesada. Elige un archivo más liviano o continúa sin logo.',
            ],
            app()->getLocale(),
        );
    }

    /**
     * Limita los intentos de autenticación para frenar ataques de fuerza bruta.
     * Clave por IP + correo, configurable con AUTH_RATE_LIMIT (intentos por minuto).
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('auth', function (Request $request) {
            $maxIntentos = (int) env('AUTH_RATE_LIMIT', 6);
            $correo = (string) $request->input('correo', '');

            return Limit::perMinute($maxIntentos)
                ->by(mb_strtolower($correo).'|'.$request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.',
                    ], 429);
                });
        });

        RateLimiter::for('onboarding', function (Request $request) {
            $max = (int) env('ONBOARDING_RATE_LIMIT', 30);

            return Limit::perMinute($max)
                ->by($request->ip())
                ->response(fn () => response()->json([
                    'message' => 'Demasiadas consultas. Espera un momento e inténtalo de nuevo.',
                ], 429));
        });

        RateLimiter::for('onboarding-complete', function (Request $request) {
            $max = (int) env('ONBOARDING_COMPLETE_RATE_LIMIT', 5);
            $token = (string) $request->route('token', '');

            return Limit::perMinute($max)
                ->by($request->ip().'|'.$token)
                ->response(fn () => response()->json([
                    'message' => 'Demasiados intentos de configuración. Espera un minuto e inténtalo de nuevo.',
                ], 429));
        });
    }

    /**
     * Windows/XAMPP: sin esto cURL falla al llamar APIs HTTPS (p. ej. Google OAuth).
     */
    private function configureSslCaBundle(): void
    {
        $custom = env('SSL_CA_BUNDLE');
        $candidates = array_filter([
            is_string($custom) && $custom !== '' ? $custom : null,
            base_path('certs/cacert.pem'),
            storage_path('app/cacert.pem'),
        ]);

        foreach ($candidates as $path) {
            if (is_string($path) && is_file($path)) {
                ini_set('curl.cainfo', $path);
                ini_set('openssl.cafile', $path);

                return;
            }
        }
    }
}
