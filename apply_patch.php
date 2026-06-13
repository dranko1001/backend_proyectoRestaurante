<?php

use App\Models\Master\Tenant;
use App\Support\Tenancy\TenantConnectionManager;
use Illuminate\Support\Facades\Artisan;

$tenants = Tenant::all();
echo 'Tenants encontrados: '.$tenants->count()."\n";

foreach ($tenants as $t) {
    TenantConnectionManager::connect($t);
    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => 'database/migrations/tenant_patches',
        '--force' => true,
    ]);
    echo "== {$t->db_name} ==\n";
    echo Artisan::output();
}

echo "DONE\n";
