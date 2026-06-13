<?php

namespace Tests\Unit;

use App\Models\Master\Tenant;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TenantModelAccessTest extends TestCase
{
    public function test_extend_access_reactivates_suspended_tenant(): void
    {
        Carbon::setTestNow('2026-06-01 12:00:00');

        $slug = 'cafe-'.uniqid();

        $tenant = Tenant::query()->create([
            'slug' => $slug,
            'db_name' => 'rest_'.$slug,
            'contact_email' => 'cafe@local.test',
            'status' => 'suspended',
            'access_expires_at' => '2026-05-01 00:00:00',
        ]);

        $tenant->extendAccessByMonths(2);
        $tenant->refresh();

        $this->assertSame('active', $tenant->status);
        $this->assertTrue($tenant->isAccessActive());
        $this->assertSame('2026-08-01 12:00:00', $tenant->access_expires_at->toDateTimeString());

        Carbon::setTestNow();
    }

    public function test_is_access_active_without_expiry_date(): void
    {
        $slug = 'libre-'.uniqid();

        $tenant = Tenant::query()->create([
            'slug' => $slug,
            'db_name' => 'rest_'.$slug,
            'contact_email' => 'libre@local.test',
            'status' => 'active',
            'access_expires_at' => null,
        ]);

        $this->assertTrue($tenant->isAccessActive());
        $this->assertNull($tenant->accessDaysRemaining());
    }
}
