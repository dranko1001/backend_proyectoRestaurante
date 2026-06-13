<?php

namespace App\Services\Auth;

use App\Models\Master\Tenant;
use App\Models\Usuario;
use App\Support\Tenancy\TenantConnectionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

class AdminTwoFactorService
{
    private const CHALLENGE_TTL_MINUTES = 5;

    public function __construct(
        private readonly TwoFactorAuthenticationProvider $provider,
    ) {}

    public function createChallenge(Usuario $usuario, ?string $deviceName = null): string
    {
        $token = Str::random(64);
        $tenant = app()->bound('tenant.current') ? app('tenant.current') : null;

        Cache::put($this->cacheKey($token), [
            'user_id' => $usuario->idUsuario,
            'tenant_slug' => $tenant?->slug,
            'device_name' => $deviceName,
        ], now()->addMinutes(self::CHALLENGE_TTL_MINUTES));

        return $token;
    }

    /**
     * @return array{user: Usuario, device_name: ?string}
     */
    public function resolveChallenge(string $challengeToken): array
    {
        $payload = Cache::get($this->cacheKey($challengeToken));

        if (! is_array($payload) || empty($payload['user_id'])) {
            throw ValidationException::withMessages([
                'challenge_token' => ['La sesión de verificación expiró. Vuelve a iniciar sesión.'],
            ]);
        }

        if (! empty($payload['tenant_slug'])) {
            $tenant = Tenant::query()
                ->where('slug', $payload['tenant_slug'])
                ->where('status', 'active')
                ->first();

            if ($tenant) {
                TenantConnectionManager::connect($tenant);
            }
        }

        $usuario = Usuario::query()
            ->with('cargo')
            ->where('idUsuario', $payload['user_id'])
            ->where('activo', true)
            ->first();

        if (! $usuario || $usuario->cargo?->nombre !== 'ADMINISTRADOR') {
            throw ValidationException::withMessages([
                'challenge_token' => ['No se pudo validar el administrador.'],
            ]);
        }

        return [
            'user' => $usuario,
            'device_name' => $payload['device_name'] ?? null,
        ];
    }

    public function verifyCode(Usuario $usuario, string $code): bool
    {
        if (empty($usuario->two_factor_secret)) {
            return false;
        }

        $secret = Fortify::currentEncrypter()->decrypt($usuario->two_factor_secret);

        return $this->provider->verify($secret, $code);
    }

    public function verifyRecoveryCode(Usuario $usuario, string $recoveryCode): bool
    {
        if (empty($usuario->two_factor_recovery_codes)) {
            return false;
        }

        $match = collect($usuario->recoveryCodes())->first(
            fn (string $code) => hash_equals($code, $recoveryCode)
        );

        if (! $match) {
            return false;
        }

        $usuario->replaceRecoveryCode($match);

        return true;
    }

    public function completeChallenge(string $challengeToken, ?string $code, ?string $recoveryCode): JsonResponse
    {
        $resolved = $this->resolveChallenge($challengeToken);
        /** @var Usuario $usuario */
        $usuario = $resolved['user'];

        $valid = false;
        if ($recoveryCode) {
            $valid = $this->verifyRecoveryCode($usuario, $recoveryCode);
        } elseif ($code) {
            $valid = $this->verifyCode($usuario, $code);
        }

        if (! $valid) {
            throw ValidationException::withMessages([
                'code' => ['El código de verificación no es válido.'],
            ]);
        }

        Cache::forget($this->cacheKey($challengeToken));

        return $this->tokenResponse($usuario, $resolved['device_name']);
    }

    public function tokenResponse(Usuario $usuario, ?string $deviceName = null, ?Request $request = null): JsonResponse
    {
        $usuario->loadMissing('cargo');

        $device = $deviceName
            ?? $request?->input('device_name')
            ?? ($request?->userAgent() ?: 'web');

        $token = $usuario->createToken($device)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->serializeUser($usuario),
            'two_factor' => false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeUser(Usuario $usuario): array
    {
        return [
            'idUsuario' => $usuario->idUsuario,
            'nombre' => $usuario->nombre,
            'apellido' => $usuario->apellido,
            'correo' => $usuario->correo,
            'cargos_idCargo' => $usuario->cargos_idCargo,
            'rol' => $usuario->cargo?->nombre,
            'two_factor_enabled' => $usuario->hasEnabledTwoFactorAuthentication(),
        ];
    }

    private function cacheKey(string $token): string
    {
        return 'admin_2fa_challenge:'.hash('sha256', $token);
    }
}
