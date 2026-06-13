<?php

namespace App\Support\Tenancy;

use Illuminate\Http\Request;

class SubdomainResolver
{
    public static function fromRequest(Request $request): ?string
    {
        $host = strtolower($request->getHost());
        $base = strtolower(TenantUrl::baseDomain());

        if ($host === $base) {
            return null;
        }

        $suffix = '.'.$base;
        if (! str_ends_with($host, $suffix)) {
            return null;
        }

        $sub = substr($host, 0, -strlen($suffix));

        if ($sub === '' || str_contains($sub, '.')) {
            return null;
        }

        return $sub;
    }

    /**
     * Slug por cabecera o query — solo en local/testing (desarrollo sin subdominio).
     * En producción el tenant se resuelve únicamente por subdominio del host.
     */
    public static function devSlugFromRequest(Request $request): ?string
    {
        if (! app()->environment(['local', 'testing'])) {
            return null;
        }

        $header = $request->header('X-Tenant-Slug');
        if (is_string($header) && $header !== '') {
            return strtolower(trim($header));
        }

        $query = $request->query('tenant');
        if (is_string($query) && $query !== '') {
            return strtolower(trim($query));
        }

        return null;
    }
}
