<?php

namespace App\Services\Tenancy;

use App\Models\Cargo;
use App\Models\Master\Tenant;
use App\Models\RestauranteConfig;
use App\Models\Usuario;
use App\Support\Tenancy\TenantConnectionManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class TenantProvisioner
{
    /**
     * @param  array{
     *   nombre_comercial: string,
     *   nit_o_documento?: string|null,
     *   telefono?: string|null,
     *   direccion?: string|null,
     *   admin_nombre: string,
     *   admin_apellido: string,
     *   admin_correo: string,
     *   admin_password: string,
     *   admin_cedula?: string|null,
     *   admin_telefono?: string|null,
     *   logo_path?: string|null,
     * }  $payload
     */
    public function provision(Tenant $tenant, array $payload): void
    {
        // Estado REAL antes de marcar 'provisioning': nunca debemos borrar la BD
        // de un tenant ya 'active'. Solo se reintenta sobre 'pending'/'failed'.
        $estadoPrevio = $tenant->status;

        if ($estadoPrevio === 'active') {
            throw new \RuntimeException('El tenant ya está activo; aprovisionar de nuevo borraría sus datos. Operación bloqueada.');
        }

        $tenant->update(['status' => 'provisioning', 'provision_error' => null]);

        try {
            // Limpieza segura solo cuando aún no se había completado el aprovisionamiento.
            if (in_array($estadoPrevio, ['failed', 'provisioning', 'pending'], true)) {
                $this->dropDatabaseIfExists($tenant->db_name);
            }

            $this->createDatabaseIfMissing($tenant->db_name);
            $this->cloneSchemaFromTemplate($tenant->db_name);
            TenantConnectionManager::connect($tenant);
            $this->runTenantPatchMigrations();
            $this->seedTenantBootstrap($payload);
            $this->finalizeTenantData($payload);

            $months = (int) ($tenant->license_months ?? 0);
            if ($months <= 0) {
                $months = (int) config('tenancy.default_license_months', 1);
            }

            $tenant->update([
                'status' => 'active',
                'nombre_comercial' => $payload['nombre_comercial'],
                'provisioned_at' => now(),
                'onboarding_completed_at' => now(),
                'access_expires_at' => $months > 0 ? now()->addMonths($months) : null,
                'access_cancel_at_period_end' => false,
                'provision_error' => null,
            ]);
        } catch (Throwable $e) {
            Log::error('Tenant provision failed', [
                'tenant_id' => $tenant->id,
                'slug' => $tenant->slug,
                'error' => $e->getMessage(),
            ]);

            $tenant->update([
                'status' => 'failed',
                'provision_error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            TenantConnectionManager::disconnect();
        }
    }

    public function dropDatabaseIfExists(string $dbName): void
    {
        $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName);
        if ($safe === '' || $safe !== $dbName) {
            throw new \InvalidArgumentException('Nombre de base de datos inválido.');
        }

        $this->assertDroppableDatabase($safe);

        // El DROP siempre apunta al servidor master, igual que la creación del tenant.
        DB::connection('master')->statement("DROP DATABASE IF EXISTS `{$safe}`");
    }

    /**
     * Última barrera de seguridad: jamás borrar la BD master, la plantilla
     * ni la conexión por defecto, aunque alguien las pase por error.
     */
    private function assertDroppableDatabase(string $dbName): void
    {
        $reservadas = array_filter([
            config('database.connections.master.database'),
            config('database.connections.tenant.database'),
            config('tenancy.template_database'),
            env('DB_DATABASE'),
        ]);

        foreach ($reservadas as $reservada) {
            if (strcasecmp((string) $reservada, $dbName) === 0) {
                throw new \RuntimeException("Operación destructiva bloqueada: '{$dbName}' es una base de datos protegida.");
            }
        }
    }

    public function createDatabaseIfMissing(string $dbName): void
    {
        $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName);
        if ($safe !== $dbName) {
            throw new \InvalidArgumentException('Nombre de base de datos inválido.');
        }

        DB::connection('master')->statement(
            "CREATE DATABASE IF NOT EXISTS `{$safe}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
    }

    public function cloneSchemaFromTemplate(string $targetDb): void
    {
        $template = (string) config('tenancy.template_database');
        if ($template === '' || $template === $targetDb) {
            throw new \RuntimeException('Configura TENANT_TEMPLATE_DATABASE con una BD que tenga el esquema del restaurante.');
        }

        $tables = DB::connection('master')->select('SHOW TABLES FROM `'.$template.'`');
        $key = 'Tables_in_'.$template;

        foreach ($tables as $row) {
            $table = $row->{$key} ?? null;
            if (! is_string($table) || $table === '') {
                continue;
            }

            DB::connection('master')->statement(
                "CREATE TABLE IF NOT EXISTS `{$targetDb}`.`{$table}` LIKE `{$template}`.`{$table}`"
            );
        }
    }

    /** Parches posteriores al esquema clonado (sin migraciones Laravel base). */
    public function runTenantPatchMigrations(): void
    {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant_patches',
            '--force' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function seedTenantBootstrap(array $payload): void
    {
        Artisan::call('db:seed', [
            '--database' => 'tenant',
            '--class' => 'Database\\Seeders\\TenantBootstrapSeeder',
            '--force' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function finalizeTenantData(array $payload): void
    {
        $cargos = Cargo::query()->pluck('idCargo', 'nombre');

        RestauranteConfig::query()->updateOrCreate(
            ['idConfig' => 1],
            [
                'nombre_comercial' => $payload['nombre_comercial'],
                'nit_o_documento' => $payload['nit_o_documento'] ?? null,
                'telefono' => $payload['telefono'] ?? null,
                'direccion' => $payload['direccion'] ?? null,
                'logo_url' => $payload['logo_url'] ?? null,
                'actualizado_en' => now(),
            ],
        );

        Usuario::query()->create([
            'nombre' => $payload['admin_nombre'],
            'apellido' => $payload['admin_apellido'],
            'cedula' => $payload['admin_cedula'] ?? '0000000000',
            'telefono' => $payload['admin_telefono'] ?? '3000000000',
            'correo' => $payload['admin_correo'],
            'password' => $payload['admin_password'],
            'cargos_idCargo' => $cargos['ADMINISTRADOR'] ?? 4,
            'activo' => true,
            'creado_en' => now(),
        ]);
    }

    public static function storeLogoForTenant(Tenant $tenant, $uploadedFile): ?string
    {
        if (! $uploadedFile) {
            return null;
        }

        return \App\Support\PublicStorage::storeTenantLogo($tenant->slug, $uploadedFile);
    }
}
