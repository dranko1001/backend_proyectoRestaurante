<?php

namespace Tests\Unit;

use App\Support\Tenancy\SubdomainResolver;
use Illuminate\Http\Request;
use Tests\TestCase;

class SubdomainResolverTest extends TestCase
{
    public function test_dev_slug_header_works_in_local(): void
    {
        $this->app->detectEnvironment(fn () => 'local');

        $request = Request::create('http://127.0.0.1/api/test', 'GET', [], [], [], [
            'HTTP_X_TENANT_SLUG' => 'chispa',
        ]);

        $this->assertSame('chispa', SubdomainResolver::devSlugFromRequest($request));
    }

    public function test_dev_slug_header_ignored_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $request = Request::create('http://127.0.0.1/api/test', 'GET', [], [], [], [
            'HTTP_X_TENANT_SLUG' => 'chispa',
        ]);

        $this->assertNull(SubdomainResolver::devSlugFromRequest($request));
    }
}
