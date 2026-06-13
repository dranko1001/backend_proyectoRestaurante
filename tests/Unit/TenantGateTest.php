<?php

namespace Tests\Unit;

use App\Models\Master\Tenant;
use App\Support\Tenancy\TenantGate;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TenantGateTest extends TestCase
{
    private TenantGate $gate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gate = new TenantGate;
    }

    public function test_suspended_tenant_is_denied(): void
    {
        $tenant = $this->createTenant(['status' => 'suspended']);

        $result = $this->gate->resolveAccessibleTenant($tenant->slug);

        $this->assertArrayHasKey('code', $result);
        $this->assertSame(TenantGate::CODE_SUSPENDED, $result['code']);
        $this->assertSame(403, $result['status']);
    }

    public function test_expired_license_auto_suspends_and_denies(): void
    {
        Carbon::setTestNow('2026-06-01 12:00:00');

        $tenant = $this->createTenant([
            'status' => 'active',
            'access_expires_at' => '2026-05-01 00:00:00',
        ]);

        $result = $this->gate->resolveAccessibleTenant($tenant->slug);

        $this->assertSame(TenantGate::CODE_SUSPENDED, $result['code']);
        $this->assertSame('suspended', $tenant->fresh()->status);

        Carbon::setTestNow();
    }

    public function test_active_tenant_with_future_license_is_allowed(): void
    {
        Carbon::setTestNow('2026-06-01 12:00:00');

        $tenant = $this->createTenant([
            'status' => 'active',
            'access_expires_at' => '2026-12-01 00:00:00',
        ]);

        $result = $this->gate->resolveAccessibleTenant($tenant->slug);

        $this->assertArrayHasKey('tenant', $result);
        $this->assertSame($tenant->id, $result['tenant']->id);

        Carbon::setTestNow();
    }

    public function test_unknown_slug_returns_not_found(): void
    {
        $result = $this->gate->resolveAccessibleTenant('no-existe');

        $this->assertSame(TenantGate::CODE_NOT_FOUND, $result['code']);
        $this->assertSame(404, $result['status']);
    }

    public function test_reserved_subdomain_is_rejected(): void
    {
        $result = $this->gate->resolveAccessibleTenant('master');

        $this->assertSame(TenantGate::CODE_RESERVED_SUBDOMAIN, $result['code']);
        $this->assertSame(404, $result['status']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createTenant(array $overrides = []): Tenant
    {
        $slug = $overrides['slug'] ?? ('demo-'.uniqid());

        return Tenant::query()->create(array_merge([
            'slug' => $slug,
            'db_name' => 'rest_'.$slug,
            'contact_email' => $slug.'@local.test',
            'nombre_comercial' => 'Demo',
            'status' => 'active',
            'access_expires_at' => null,
        ], $overrides, ['slug' => $slug, 'db_name' => 'rest_'.$slug]));
    }
}
