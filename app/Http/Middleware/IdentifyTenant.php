<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    public function __construct(private readonly TenantGate $tenantGate) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! TenantContext::isMulti()) {
            return $next($request);
        }

        if ($request->is('api/master/*')) {
            app()->instance('tenant.context', 'master');
            config(['database.default' => 'master']);

            return $next($request);
        }

        $slug = $this->tenantGate->resolveSlugFromRequest($request);
        $connected = $this->tenantGate->connectAccessibleTenant($slug ?? '');

        if (! $connected instanceof \App\Models\Master\Tenant) {
            return $this->tenantGate->denyJsonResponse($connected);
        }

        return $next($request);
    }
}
