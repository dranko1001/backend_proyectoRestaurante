<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantUrl;
use Illuminate\Http\JsonResponse;

class MasterPlatformController extends Controller
{
    public function settings(): JsonResponse
    {
        return response()->json([
            'data' => [
                'tenancy_mode' => (string) config('tenancy.mode', 'single'),
                'base_domain' => TenantUrl::baseDomain(),
                'master_subdomain' => (string) config('tenancy.master_subdomain', 'master'),
                'default_license_months' => (int) config('tenancy.default_license_months', 1),
                'onboarding_ttl_hours' => (int) config('tenancy.onboarding_token_ttl_hours', 72),
                'database_prefix' => (string) config('tenancy.database_prefix', 'rest_'),
            ],
        ]);
    }
}
