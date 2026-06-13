<?php

namespace Tests\Feature;

use App\Models\Master\MasterUser;
use App\Models\Master\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MasterTenantAccessTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = ['master'];

    private function masterAuthHeaders(): array
    {
        config(['database.default' => 'master']);

        $user = MasterUser::query()->firstOrCreate(
            ['email' => 'master-access-test@local.test'],
            ['name' => 'Master Test', 'password' => 'password12345', 'activo' => true],
        );

        $token = $user->createToken('phpunit')->plainTextToken;

        return ['Authorization' => 'Bearer '.$token];
    }

    public function test_suspend_schedules_cancellation_when_license_is_future(): void
    {
        $tenant = Tenant::query()->create([
            'slug' => 'test-cancel-'.uniqid(),
            'db_name' => 'rest_test_cancel',
            'contact_email' => 'cancel@test.local',
            'status' => 'active',
            'onboarding_completed_at' => now(),
            'access_expires_at' => now()->addMonth(),
            'access_cancel_at_period_end' => false,
        ]);

        $response = $this->postJson("/api/master/tenants/{$tenant->id}/suspend", [], $this->masterAuthHeaders());

        $response->assertOk();
        $tenant->refresh();

        $this->assertSame('active', $tenant->status);
        $this->assertTrue($tenant->access_cancel_at_period_end);
        $this->assertTrue($tenant->isAccessScheduledForCancellation());
    }

    public function test_extend_access_clears_scheduled_cancellation_and_reactivates(): void
    {
        $tenant = Tenant::query()->create([
            'slug' => 'test-extend-'.uniqid(),
            'db_name' => 'rest_test_extend',
            'contact_email' => 'extend@test.local',
            'status' => 'active',
            'onboarding_completed_at' => now(),
            'access_expires_at' => now()->addDays(10),
            'access_cancel_at_period_end' => true,
        ]);

        $response = $this->postJson("/api/master/tenants/{$tenant->id}/extend-access", [
            'months' => 3,
        ], $this->masterAuthHeaders());

        $response->assertOk();
        $tenant->refresh();

        $this->assertSame('active', $tenant->status);
        $this->assertFalse($tenant->access_cancel_at_period_end);
        $this->assertTrue($tenant->access_expires_at->isFuture());
    }

    public function test_suspend_immediately_when_no_future_expiry(): void
    {
        $tenant = Tenant::query()->create([
            'slug' => 'test-imm-'.uniqid(),
            'db_name' => 'rest_test_imm',
            'contact_email' => 'imm@test.local',
            'status' => 'active',
            'onboarding_completed_at' => now(),
            'access_expires_at' => null,
            'access_cancel_at_period_end' => false,
        ]);

        $response = $this->postJson("/api/master/tenants/{$tenant->id}/suspend", [], $this->masterAuthHeaders());

        $response->assertOk();
        $tenant->refresh();

        $this->assertSame('suspended', $tenant->status);
        $this->assertFalse($tenant->access_cancel_at_period_end);
    }

    public function test_invitation_requires_and_stores_license_months(): void
    {
        $slug = 'inv-lic-'.uniqid();

        $response = $this->postJson('/api/master/invitations', [
            'email' => 'invita@test.local',
            'slug' => $slug,
            'license_months' => 3,
        ], $this->masterAuthHeaders());

        $response->assertCreated();

        $tenant = Tenant::query()->where('slug', $slug)->first();
        $this->assertNotNull($tenant);
        $this->assertSame(3, (int) $tenant->license_months);
    }
}
