<?php

namespace App\Support\Tenancy;

use App\Models\Master\Tenant;

class TenantContext
{
    public static function current(): ?Tenant
    {
        $tenant = app()->bound('tenant.current') ? app('tenant.current') : null;

        return $tenant instanceof Tenant ? $tenant : null;
    }

    public static function isMasterRequest(): bool
    {
        return app()->bound('tenant.context') && app('tenant.context') === 'master';
    }

    public static function mode(): string
    {
        return (string) config('tenancy.mode', 'single');
    }

    public static function isMulti(): bool
    {
        return self::mode() === 'multi';
    }
}
