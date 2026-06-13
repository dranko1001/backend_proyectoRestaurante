<?php

namespace App\Support\OAuth;

class TenantOAuthState
{
    /**
     * @return array{redirect: string, tenant: string, valid: bool}
     */
    public function encode(string $redirectPath, string $tenantSlug): string
    {
        $payload = json_encode([
            'redirect' => $redirectPath,
            'tenant' => strtolower($tenantSlug),
        ], JSON_THROW_ON_ERROR);

        $encoded = $this->base64UrlEncode($payload);
        $signature = $this->sign($encoded);

        return $encoded.'.'.$signature;
    }

    /**
     * @return array{redirect: string, tenant: ?string, valid: bool}
     */
    public function decode(?string $state, callable $sanitizeRedirect): array
    {
        $fallback = [
            'redirect' => '/cliente/carta',
            'tenant' => null,
            'valid' => false,
        ];

        if (! is_string($state) || $state === '') {
            return $fallback;
        }

        $parts = explode('.', $state, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return $fallback;
        }

        [$encoded, $signature] = $parts;

        if (! hash_equals($this->sign($encoded), $signature)) {
            return $fallback;
        }

        try {
            $json = $this->base64UrlDecode($encoded);
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($data)) {
                return $fallback;
            }

            $redirect = is_string($data['redirect'] ?? null) ? $data['redirect'] : '/cliente/carta';
            $tenant = is_string($data['tenant'] ?? null) ? strtolower(trim($data['tenant'])) : null;

            return [
                'redirect' => $sanitizeRedirect($redirect),
                'tenant' => $tenant !== '' ? $tenant : null,
                'valid' => $tenant !== null && $tenant !== '',
            ];
        } catch (\Throwable) {
            return $fallback;
        }
    }

    private function sign(string $encodedPayload): string
    {
        return hash_hmac('sha256', $encodedPayload, $this->signingKey());
    }

    private function signingKey(): string
    {
        $key = (string) config('app.key');

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            return $decoded !== false ? $decoded : $key;
        }

        return $key;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $b64 = strtr($value, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }

        $decoded = base64_decode($b64, true);
        if ($decoded === false) {
            throw new \RuntimeException('Invalid base64 payload.');
        }

        return $decoded;
    }
}
