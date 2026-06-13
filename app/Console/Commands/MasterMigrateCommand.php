<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class MasterMigrateCommand extends Command
{
    protected $signature = 'master:migrate {--seed : Crear usuario master por defecto}';

    protected $description = 'Crea la BD master (si no existe) y ejecuta migraciones de la plataforma';

    public function handle(): int
    {
        $dbName = preg_replace('/[^a-zA-Z0-9_]/', '', (string) config('database.connections.master.database'));

        DB::statement(
            "CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );

        $this->info("BD master: {$dbName}");

        Artisan::call('migrate', [
            '--database' => 'master',
            '--path' => 'database/migrations/master',
            '--force' => true,
        ]);

        $this->line(Artisan::output());

        if ($this->option('seed')) {
            Artisan::call('db:seed', [
                '--database' => 'master',
                '--class' => 'Database\\Seeders\\MasterDatabaseSeeder',
                '--force' => true,
            ]);
            $this->line(Artisan::output());
            $this->info('Usuario master: '.env('MASTER_ADMIN_EMAIL', 'master@local.test'));
        }

        return self::SUCCESS;
    }
}
