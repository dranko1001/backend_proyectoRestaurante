<?php

namespace App\Services\Auth;

use App\Mail\MasterTwoFactorLoginMail;
use App\Models\Master\MasterUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

class MasterTwoFactorService
{
    private const CHALLENGE_TTL_MINUTES = 5;

    public function __construct(
        private readonly TwoFactorAuthenticationProvider $provider,
        private readonly Google2FA $google2fa,
    ) {}

    public function createChallenge(MasterUser $user, ?string $deviceName = null): string
    {
        $token = Str::random(64);

        Cache::put($this->cacheKey($token), [
            'user_id' => $user->id,
            'device_name' => $deviceName,
        ], now()->addMinutes(self::CHALLENGE_TTL_MINUTES));

        return $token;
    }

    public function sendLoginCodeByEmail(MasterUser $user): bool
    {
        $code = $this->currentTotpCode($user);
        if ($code === null) {
            return false;
        }

        $rateKey = 'master-2fa-email:'.$user->id;
        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            return false;
        }

        try {
            Mail::to($user->email)->send(new MasterTwoFactorLoginMail(
                userName: (string) ($user->name ?: 'Master'),
                code: $code,
                validSeconds: (int) $this->google2fa->getKeyRegeneration(),
            ));
            RateLimiter::hit($rateKey, 300);
        } catch (\Throwable $e) {
            Log::warning('No se pudo enviar código 2FA Master por correo.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        return true;
    }

    public function sendLoginCodeForChallenge(string $challengeToken): bool
    {
        $resolved = $this->resolveChallenge($challengeToken);

        return $this->sendLoginCodeByEmail($resolved['user']);
    }

    public function currentTotpCode(MasterUser $user): ?string
    {
        if (empty($user->two_factor_secret)) {
            return null;
        }

        $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);

        return (string) $this->google2fa->getCurrentOtp($secret);
    }

    /**
     * @return array{user: MasterUser, device_name: ?string}
     */
    public function resolveChallenge(string $challengeToken): array
    {
        config(['database.default' => 'master']);

        $payload = Cache::get($this->cacheKey($challengeToken));

        if (! is_array($payload) || empty($payload['user_id'])) {
            throw ValidationException::withMessages([
                'challenge_token' => ['La sesión de verificación expiró. Vuelve a iniciar sesión.'],
            ]);
        }

        $user = MasterUser::query()
            ->where('id', $payload['user_id'])
            ->where('activo', true)
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'challenge_token' => ['No se pudo validar el usuario master.'],
            ]);
        }

        return [
            'user' => $user,
            'device_name' => $payload['device_name'] ?? null,
        ];
    }

    public function verifyCode(MasterUser $user, string $code): bool
    {
        if (empty($user->two_factor_secret)) {
            return false;
        }

        $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);

        return $this->provider->verify($secret, $code);
    }

    public function verifyRecoveryCode(MasterUser $user, string $recoveryCode): bool
    {
        if (empty($user->two_factor_recovery_codes)) {
            return false;
        }

        $match = collect($user->recoveryCodes())->first(
            fn (string $code) => hash_equals($code, $recoveryCode)
        );

        if (! $match) {
            return false;
        }

        $user->replaceRecoveryCode($match);

        return true;
    }

    public function completeChallenge(string $challengeToken, ?string $code, ?string $recoveryCode): JsonResponse
    {
        $resolved = $this->resolveChallenge($challengeToken);
        /** @var MasterUser $user */
        $user = $resolved['user'];

        $valid = false;
        if ($recoveryCode) {
            $valid = $this->verifyRecoveryCode($user, $recoveryCode);
        } elseif ($code) {
            $valid = $this->verifyCode($user, $code);
        }

        if (! $valid) {
            throw ValidationException::withMessages([
                'code' => ['El código de verificación no es válido.'],
            ]);
        }

        Cache::forget($this->cacheKey($challengeToken));

        return $this->tokenResponse($user, $resolved['device_name']);
    }

    public function tokenResponse(MasterUser $user, ?string $deviceName = null): JsonResponse
    {
        config(['database.default' => 'master']);

        $device = $deviceName ?: 'master-web';
        $token = $user->createToken($device)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->serializeUser($user),
            'two_factor' => false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeUser(MasterUser $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'two_factor_enabled' => $user->hasEnabledTwoFactorAuthentication(),
        ];
    }

    private function cacheKey(string $token): string
    {
        return 'master_2fa_challenge:'.hash('sha256', $token);
    }
}
