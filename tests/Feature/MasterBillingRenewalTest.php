<?php

namespace Tests\Feature;

use App\Models\Cargo;
use App\Models\Master\MasterUser;
use App\Models\Master\PlatformBillingSetting;
use App\Models\Master\SubscriptionRenewalRequest;
use App\Models\Master\Tenant;
use App\Models\Usuario;
use App\Support\Tenancy\TenantConnectionManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MasterBillingRenewalTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = ['master'];

    private function masterAuthHeaders(): array
    {
        config(['database.default' => 'master']);

        $user = MasterUser::query()->firstOrCreate(
            ['email' => 'master-billing-test@local.test'],
            ['name' => 'Master Billing', 'password' => 'password12345', 'activo' => true],
        );

        $token = $user->createToken('phpunit')->plainTextToken;

        return ['Authorization' => 'Bearer '.$token];
    }

    public function test_master_can_update_billing_settings(): void
    {
        $response = $this->postJson('/api/master/billing/settings', [
            'nequi_key' => '3001112233',
            'payment_instructions' => 'Paga con concepto: renovación SaaS',
            'price_1_month_cop' => 50000,
            'price_3_months_cop' => 140000,
            'price_6_months_cop' => 270000,
            'price_12_months_cop' => 500000,
        ], $this->masterAuthHeaders());

        $response->assertOk()
            ->assertJsonPath('data.nequi_key', '3001112233')
            ->assertJsonPath('data.package_prices.1', 50000)
            ->assertJsonPath('data.package_prices.3', 140000);

        $this->assertDatabaseHas('platform_billing_settings', [
            'nequi_key' => '3001112233',
            'price_1_month_cop' => 50000,
            'price_3_months_cop' => 140000,
        ], 'master');
    }

    public function test_admin_can_submit_and_master_can_approve_renewal(): void
    {
        PlatformBillingSetting::query()->delete();
        PlatformBillingSetting::query()->create([
            'nequi_key' => '3009998877',
            'price_1_month_cop' => 50000,
            'price_3_months_cop' => 140000,
            'price_6_months_cop' => 270000,
            'price_12_months_cop' => 500000,
            'price_per_month_cop' => 50000,
            'payment_instructions' => 'Referencia obligatoria',
        ]);

        $slug = 'renew-'.uniqid();
        $tenant = Tenant::query()->create([
            'slug' => $slug,
            'db_name' => env('DB_DATABASE', 'restaurante'),
            'contact_email' => $slug.'@local.test',
            'status' => 'active',
            'onboarding_completed_at' => now()->subMonth(),
            'access_expires_at' => now()->addDays(5),
        ]);

        TenantConnectionManager::connect($tenant);

        $adminCargo = Cargo::query()->firstOrCreate(
            ['nombre' => 'ADMINISTRADOR'],
            ['descripcion' => 'Admin test']
        );

        $admin = Usuario::query()->create([
            'nombre' => 'Adm',
            'apellido' => 'Renew',
            'cedula' => 'RNW'.random_int(100000, 999999),
            'telefono' => '3000000099',
            'correo' => $slug.'-admin@local.test',
            'password' => Hash::make('password123'),
            'cargos_idCargo' => $adminCargo->idCargo,
            'activo' => true,
            'creado_en' => now(),
        ]);

        $adminToken = $admin->createToken('renew-test')->plainTextToken;
        $adminHeaders = [
            'X-Tenant-Slug' => $slug,
            'Authorization' => 'Bearer '.$adminToken,
        ];

        $submit = $this->withHeaders($adminHeaders)->postJson('/api/admin/suscripcion/renovacion', [
            'months' => 3,
            'payment_reference' => 'M123456789',
            'admin_note' => 'Pago 10:30 am',
        ]);

        $submit->assertCreated()
            ->assertJsonPath('data.months', 3)
            ->assertJsonPath('data.amount_cop', 140000)
            ->assertJsonPath('data.status', 'pending');

        $renewalId = $submit->json('data.id');
        $expiresBefore = $tenant->fresh()->access_expires_at;

        $approve = $this->postJson(
            "/api/master/billing/renewal-requests/{$renewalId}/approve",
            [],
            $this->masterAuthHeaders()
        );

        $approve->assertOk();

        $tenant->refresh();
        $this->assertTrue($tenant->access_expires_at->gt($expiresBefore));
        $this->assertSame('approved', SubscriptionRenewalRequest::query()->find($renewalId)?->status);
    }

    public function test_admin_cannot_submit_second_pending_renewal(): void
    {
        $slug = 'dup-renew-'.uniqid();
        $tenant = Tenant::query()->create([
            'slug' => $slug,
            'db_name' => env('DB_DATABASE', 'restaurante'),
            'contact_email' => $slug.'@local.test',
            'status' => 'active',
            'onboarding_completed_at' => now(),
            'access_expires_at' => now()->addMonth(),
        ]);

        SubscriptionRenewalRequest::query()->create([
            'tenant_id' => $tenant->id,
            'months' => 1,
            'amount_cop' => 50000,
            'payment_reference' => 'EXISTING',
            'status' => SubscriptionRenewalRequest::STATUS_PENDING,
        ]);

        TenantConnectionManager::connect($tenant);

        $adminCargo = Cargo::query()->firstOrCreate(
            ['nombre' => 'ADMINISTRADOR'],
            ['descripcion' => 'Admin test']
        );

        $admin = Usuario::query()->create([
            'nombre' => 'Adm',
            'apellido' => 'Dup',
            'cedula' => 'DUP'.random_int(100000, 999999),
            'telefono' => '3000000088',
            'correo' => $slug.'-admin@local.test',
            'password' => Hash::make('password123'),
            'cargos_idCargo' => $adminCargo->idCargo,
            'activo' => true,
            'creado_en' => now(),
        ]);

        $response = $this->withHeaders([
            'X-Tenant-Slug' => $slug,
            'Authorization' => 'Bearer '.$admin->createToken('dup')->plainTextToken,
        ])->postJson('/api/admin/suscripcion/renovacion', [
            'months' => 1,
            'payment_reference' => 'NEWREF123',
        ]);

        $response->assertStatus(422);
    }

    public function test_master_can_list_renewal_history_with_filters(): void
    {
        $tenantA = Tenant::query()->create([
            'slug' => 'hist-a-'.uniqid(),
            'db_name' => 'hist-a-'.uniqid(),
            'contact_email' => 'hist-a@local.test',
            'nombre_comercial' => 'Restaurante Alpha',
            'status' => 'active',
            'onboarding_completed_at' => now(),
            'access_expires_at' => now()->addMonth(),
        ]);

        $tenantB = Tenant::query()->create([
            'slug' => 'hist-b-'.uniqid(),
            'db_name' => 'hist-b-'.uniqid(),
            'contact_email' => 'hist-b@local.test',
            'nombre_comercial' => 'Restaurante Beta',
            'status' => 'active',
            'onboarding_completed_at' => now(),
            'access_expires_at' => now()->addMonth(),
        ]);

        SubscriptionRenewalRequest::query()->create([
            'tenant_id' => $tenantA->id,
            'months' => 3,
            'amount_cop' => 140000,
            'payment_reference' => 'ALPHA-REF-001',
            'status' => SubscriptionRenewalRequest::STATUS_APPROVED,
            'reviewed_at' => now()->subDay(),
        ]);

        SubscriptionRenewalRequest::query()->create([
            'tenant_id' => $tenantB->id,
            'months' => 1,
            'amount_cop' => 50000,
            'payment_reference' => 'BETA-REF-002',
            'status' => SubscriptionRenewalRequest::STATUS_REJECTED,
            'reviewed_at' => now(),
        ]);

        $headers = $this->masterAuthHeaders();

        $all = $this->getJson('/api/master/billing/renewal-history', $headers);
        $all->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'total']]);
        $this->assertGreaterThanOrEqual(2, count($all->json('data')));

        $approved = $this->getJson('/api/master/billing/renewal-history?status=approved&q=Alpha', $headers);
        $approved->assertOk();
        $this->assertNotEmpty($approved->json('data'));
        $this->assertTrue(
            collect($approved->json('data'))->every(fn (array $row) => $row['status'] === 'approved')
        );

        $pendingOnly = $this->getJson('/api/master/billing/renewal-requests', $headers);
        $pendingOnly->assertOk();
        $this->assertTrue(
            collect($pendingOnly->json('data'))->every(fn (array $row) => $row['status'] === 'pending')
        );
    }
}
