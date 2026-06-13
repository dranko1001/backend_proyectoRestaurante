<?php

namespace Tests\Feature;

use App\Models\Master\Tenant;
use App\Support\Tenancy\TenantGate;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TenantApiGateTest extends TestCase
{
    public function test_api_returns_403_with_code_for_suspended_tenant(): void
    {
        $slug = 'chispa-'.uniqid();

        Tenant::query()->create([
            'slug' => $slug,
            'db_name' => 'rest_'.$slug,
            'contact_email' => $slug.'@local.test',
            'status' => 'suspended',
        ]);

        $response = $this->withHeaders(['X-Tenant-Slug' => $slug])
            ->getJson('/api/public/productos-carta');

        $response->assertStatus(403)
            ->assertJson([
                'code' => TenantGate::CODE_SUSPENDED,
            ]);
    }

    public function test_api_returns_403_with_code_for_expired_license(): void
    {
        Carbon::setTestNow('2026-06-01 12:00:00');

        $slug = 'vencido-'.uniqid();

        Tenant::query()->create([
            'slug' => $slug,
            'db_name' => 'rest_'.$slug,
            'contact_email' => $slug.'@local.test',
            'status' => 'active',
            'access_expires_at' => '2026-05-01 00:00:00',
        ]);

        $response = $this->withHeaders(['X-Tenant-Slug' => $slug])
            ->getJson('/api/public/productos-carta');

        $response->assertStatus(403)
            ->assertJson([
                'code' => TenantGate::CODE_SUSPENDED,
            ]);

        $this->assertSame('suspended', Tenant::query()->where('slug', $slug)->value('status'));

        Carbon::setTestNow();
    }
}
