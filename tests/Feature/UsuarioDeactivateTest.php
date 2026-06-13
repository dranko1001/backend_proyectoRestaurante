<?php

namespace Tests\Feature;

use App\Models\Cargo;
use App\Models\Master\Tenant;
use App\Models\Usuario;
use App\Support\Tenancy\TenantConnectionManager;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsuarioDeactivateTest extends TestCase
{
    public function test_deactivating_user_revokes_sanctum_tokens(): void
    {
        $slug = 'deact-'.uniqid();

        $tenant = Tenant::query()->create([
            'slug' => $slug,
            'db_name' => env('DB_DATABASE', 'restaurante'),
            'contact_email' => $slug.'@local.test',
            'status' => 'active',
        ]);

        TenantConnectionManager::connect($tenant);

        $cargo = Cargo::query()->firstOrCreate(
            ['nombre' => 'MESERO'],
            ['descripcion' => 'Mesero test']
        );

        $mesero = Usuario::query()->create([
            'nombre' => 'Mes',
            'apellido' => 'Test',
            'cedula' => 'MES'.random_int(100000, 999999),
            'telefono' => '3000000001',
            'correo' => $slug.'-mesero@local.test',
            'password' => Hash::make('password123'),
            'cargos_idCargo' => $cargo->idCargo,
            'activo' => true,
            'creado_en' => now(),
        ]);

        $mesero->createToken('test');
        $this->assertSame(1, $mesero->tokens()->count());

        $adminCargo = Cargo::query()->firstOrCreate(
            ['nombre' => 'ADMINISTRADOR'],
            ['descripcion' => 'Admin test']
        );

        $admin = Usuario::query()->create([
            'nombre' => 'Adm',
            'apellido' => 'Test',
            'cedula' => 'ADM'.random_int(100000, 999999),
            'telefono' => '3000000002',
            'correo' => $slug.'-admin@local.test',
            'password' => Hash::make('password123'),
            'cargos_idCargo' => $adminCargo->idCargo,
            'activo' => true,
            'creado_en' => now(),
        ]);

        $adminToken = $admin->createToken('admin-test')->plainTextToken;

        $this->withHeaders([
            'X-Tenant-Slug' => $slug,
            'Authorization' => 'Bearer '.$adminToken,
        ])
            ->patchJson("/api/admin/usuarios/{$mesero->idUsuario}/activo", ['activo' => false])
            ->assertOk();

        $this->assertSame(0, $mesero->fresh()->tokens()->count());
        $this->assertFalse($mesero->fresh()->activo);
    }

    public function test_inactive_user_blocked_by_role_middleware(): void
    {
        $slug = 'inactive-'.uniqid();

        Tenant::query()->create([
            'slug' => $slug,
            'db_name' => env('DB_DATABASE', 'restaurante'),
            'contact_email' => $slug.'@local.test',
            'status' => 'active',
        ]);

        TenantConnectionManager::connect(Tenant::query()->where('slug', $slug)->first());

        $cargo = Cargo::query()->firstOrCreate(['nombre' => 'MESERO'], ['descripcion' => 'Mesero']);

        $mesero = Usuario::query()->create([
            'nombre' => 'Ina',
            'apellido' => 'Ctivo',
            'cedula' => 'INA'.random_int(100000, 999999),
            'telefono' => '3000000003',
            'correo' => $slug.'@local.test',
            'password' => Hash::make('password123'),
            'cargos_idCargo' => $cargo->idCargo,
            'activo' => false,
            'creado_en' => now(),
        ]);

        $token = $mesero->createToken('test')->plainTextToken;

        $this->call(
            'GET',
            '/api/mesero/mesas',
            [],
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_TENANT_SLUG' => $slug,
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ]
        )
            ->assertStatus(403)
            ->assertJson(['message' => 'Tu cuenta está inactiva.']);
    }
}
