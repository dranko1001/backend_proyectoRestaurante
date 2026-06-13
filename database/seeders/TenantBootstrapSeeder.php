<?php

namespace Database\Seeders;

use App\Models\Cargo;
use Illuminate\Database\Seeder;

/**
 * Datos mínimos en cada BD de cliente (sin demos).
 */
class TenantBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['CLIENTE', 'MESERO', 'COCINERO', 'CAJERO', 'ADMINISTRADOR'] as $nombre) {
            Cargo::query()->firstOrCreate(['nombre' => $nombre]);
        }
    }
}
