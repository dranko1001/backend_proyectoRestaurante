<?php

namespace App\Support\Tenancy;

use App\Models\Master\Tenant;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class TenantConnectionManager
{
    public static function connect(Tenant $tenant): void
    {
        Config::set('database.connections.tenant.database', $tenant->db_name);
        DB::purge('tenant');
        DB::reconnect('tenant');
        Config::set('database.default', 'tenant');
        app()->instance('tenant.current', $tenant);
    }

    public static function disconnect(): void
    {
        app()->forgetInstance('tenant.current');
        Config::set('database.default', config('tenancy.mode') === 'multi' ? 'master' : env('DB_CONNECTION', 'mysql'));
    }
}
