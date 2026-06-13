<?php

namespace App\Support\OAuth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class OAuthExchangeCode
{
    private const TTL_SECONDS = 120;

    public function issue(string $token, string $tenantSlug): string
    {
        $code = Str::random(64);

        Cache::put($this->cacheKey($code), [
            'token' => $token,
            'tenant_slug' => $tenantSlug,
        ], now()->addSeconds(self::TTL_SECONDS));

        return $code;
    }

    public function redeem(string $code, ?string $expectedTenantSlug): ?string
    {
        $payload = Cache::pull($this->cacheKey($code));

        if (! is_array($payload) || empty($payload['token'])) {
            return null;
        }

        if ($expectedTenantSlug !== null
            && (! isset($payload['tenant_slug']) || $payload['tenant_slug'] !== $expectedTenantSlug)) {
            return null;
        }

        return (string) $payload['token'];
    }

    private function cacheKey(string $code): string
    {
        return 'oauth_exchange:'.hash('sha256', $code);
    }
}
