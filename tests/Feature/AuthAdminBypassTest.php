<?php

namespace Tests\Feature;

use App\Models\Cargo;
use App\Models\Master\Tenant;
use App\Models\Usuario;
use App\Support\Tenancy\TenantConnectionManager;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthAdminBypassTest extends TestCase
{
    public function test_generic_login_rejects_administrator(): void
    {
        $slug = 'auth-test-'.uniqid();

        $tenant = Tenant::query()->create([
            'slug' => $slug,
            'db_name' => env('DB_DATABASE', 'restaurante'),
            'contact_email' => $slug.'@local.test',
            'status' => 'active',
        ]);

        TenantConnectionManager::connect($tenant);

        $cargoAdmin = Cargo::query()->firstOrCreate(
            ['nombre' => 'ADMINISTRADOR'],
            ['descripcion' => 'Admin test']
        );

        $correo = $slug.'-admin@local.test';

        Usuario::query()->create([
            'nombre' => 'Admin',
            'apellido' => 'Test',
            'cedula' => 'TST'.random_int(100000, 999999),
            'telefono' => '3000000000',
            'correo' => $correo,
            'password' => Hash::make('password123'),
            'cargos_idCargo' => $cargoAdmin->idCargo,
            'activo' => true,
            'creado_en' => now(),
        ]);

        $response = $this->withHeaders(['X-Tenant-Slug' => $slug])
            ->postJson('/api/auth/login', [
                'correo' => $correo,
                'password' => 'password123',
            ]);

        $response->assertStatus(403)
            ->assertJsonFragment([
                'message' => 'Los administradores deben iniciar sesión desde /staff?rol=admin (login con verificación en dos pasos si está activa).',
            ]);
    }
}
