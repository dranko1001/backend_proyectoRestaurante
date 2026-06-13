<?php

namespace App\Console\Commands;

use App\Models\Master\Tenant;
use App\Support\Tenancy\TenantConnectionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class TenantsMigratePatchesCommand extends Command
{
    protected $signature = 'tenants:migrate-patches
                            {--slug= : Solo un tenant por slug}
                            {--status=active : Filtrar por status (active, suspended, all)}';

    protected $description = 'Ejecuta database/migrations/tenant_patches en cada BD de tenant';

    public function handle(): int
    {
        $slug = $this->option('slug');
        $status = (string) $this->option('status');

        $query = Tenant::query()->orderBy('slug');

        if ($slug) {
            $query->where('slug', $slug);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->warn('No hay tenants que migrar.');

            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $this->info("Migrando {$tenant->slug} ({$tenant->db_name})…");

            try {
                TenantConnectionManager::connect($tenant);

                Artisan::call('migrate', [
                    '--path' => 'database/migrations/tenant_patches',
                    '--force' => true,
                ]);

                $output = trim(Artisan::output());
                $this->line($output !== '' ? $output : '  Sin migraciones pendientes.');
            } catch (\Throwable $e) {
                $this->error("  Error en {$tenant->slug}: {$e->getMessage()}");
            } finally {
                TenantConnectionManager::disconnect();
            }
        }

        $this->info('Listo.');

        return self::SUCCESS;
    }
}
