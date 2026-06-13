<?php

namespace App\Support\Tenancy;

class TenantUrl
{
    public static function baseDomain(): string
    {
        return (string) config('tenancy.base_domain', 'localhost');
    }

    public static function frontendOrigin(?string $subdomain = null): string
    {
        $scheme = (string) config('tenancy.frontend_scheme', 'http');
        $port = config('tenancy.frontend_port');
        $portSuffix = $port ? ':'.$port : '';
        $domain = self::baseDomain();

        if ($subdomain) {
            $host = $subdomain.'.'.$domain;
        } else {
            $host = (string) config('tenancy.frontend_host', $domain);
        }

        return $scheme.'://'.$host.$portSuffix;
    }

    public static function appForSlug(string $slug): string
    {
        return self::frontendOrigin($slug);
    }

    public static function masterApp(): string
    {
        return self::frontendOrigin((string) config('tenancy.master_subdomain', 'master'));
    }

    /** Onboarding en dominio raíz (sin subdominio de tenant). */
    public static function onboarding(string $plainToken): string
    {
        return self::frontendOrigin().'/onboarding/'.$plainToken;
    }
}
