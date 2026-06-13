<?php

namespace Tests\Unit;

use App\Support\Auth\MasterPasswordPolicy;
use App\Support\OAuth\OAuthExchangeCode;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class Sprint1SecurityTest extends TestCase
{
    public function test_master_password_policy_rejects_weak_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(\InvalidArgumentException::class);
        MasterPasswordPolicy::assertForEnvironment('master123');
    }

    public function test_master_password_policy_allows_weak_in_local(): void
    {
        $this->app->detectEnvironment(fn () => 'local');

        MasterPasswordPolicy::assertForEnvironment('master123');
        $this->assertTrue(true);
    }

    public function test_oauth_exchange_code_is_single_use_and_tenant_scoped(): void
    {
        Cache::flush();

        $exchange = new OAuthExchangeCode;
        $code = $exchange->issue('token-abc', 'chispa');

        $this->assertSame('token-abc', $exchange->redeem($code, 'chispa'));
        $this->assertNull($exchange->redeem($code, 'chispa'));
    }

    public function test_oauth_exchange_code_rejects_wrong_tenant(): void
    {
        Cache::flush();

        $exchange = new OAuthExchangeCode;
        $code = $exchange->issue('token-abc', 'chispa');

        $this->assertNull($exchange->redeem($code, 'otro'));
    }
}
