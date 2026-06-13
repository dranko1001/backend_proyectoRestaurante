<?php

namespace App\Support\Tenancy;

use App\Models\Master\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantGate
{
    public const CODE_MISSING_SUBDOMAIN = 'tenant_missing_subdomain';

    public const CODE_RESERVED_SUBDOMAIN = 'tenant_reserved_subdomain';

    public const CODE_NOT_FOUND = 'tenant_not_found';

    public const CODE_SUSPENDED = 'tenant_suspended';

    public const CODE_LICENSE_EXPIRED = 'tenant_license_expired';

    public const CODE_INACTIVE = 'tenant_inactive';

    public function resolveSlugFromRequest(Request $request): ?string
    {
        $subdomain = SubdomainResolver::fromRequest($request);

        if ($subdomain === null) {
            $subdomain = SubdomainResolver::devSlugFromRequest($request);
        }

        if (($subdomain === null || $subdomain === '') && app()->environment('local')) {
            $fallback = trim((string) env('TENANT_DEFAULT_SLUG', ''));
            if ($fallback !== '') {
                $subdomain = strtolower($fallback);
            }
        }

        return is_string($subdomain) && $subdomain !== '' ? strtolower($subdomain) : null;
    }

    public function normalizeSlug(?string $slug): ?string
    {
        if (! is_string($slug) || trim($slug) === '') {
            return null;
        }

        return strtolower(trim($slug));
    }

    public function isReservedSlug(string $slug): bool
    {
        $masterSub = (string) config('tenancy.master_subdomain', 'master');
        $reserved = config('tenancy.reserved_subdomains', []);

        return $slug === $masterSub || in_array($slug, $reserved, true);
    }

    /**
     * @return array{tenant: Tenant}|array{message: string, code: string, status: int}
     */
    public function evaluateTenant(Tenant $tenant, bool $autoSuspendOnExpiry = true): array
    {
        if ($autoSuspendOnExpiry
            && $tenant->status === 'active'
            && $tenant->access_expires_at
            && $tenant->access_expires_at->isPast()
        ) {
            $tenant->update(['status' => 'suspended', 'access_cancel_at_period_end' => false]);
            $tenant->refresh();
        }

        if ($tenant->status === 'suspended') {
            return [
                'message' => 'El acceso a este restaurante fue desactivado. Contacta al proveedor del software.',
                'code' => self::CODE_SUSPENDED,
                'status' => 403,
            ];
        }

        if ($tenant->status !== 'active') {
            return [
                'message' => 'Restaurante no encontrado o aún no está activo.',
                'code' => self::CODE_INACTIVE,
                'status' => 404,
            ];
        }

        if ($tenant->access_expires_at && $tenant->access_expires_at->isPast()) {
            return [
                'message' => 'La licencia de este restaurante venció. Contacta al proveedor para renovar el acceso.',
                'code' => self::CODE_LICENSE_EXPIRED,
                'status' => 403,
            ];
        }

        return ['tenant' => $tenant];
    }

    /**
     * @return array{tenant: Tenant}|array{message: string, code: string, status: int}
     */
    public function resolveAccessibleTenant(?string $slug, bool $autoSuspendOnExpiry = true): array
    {
        $slug = $this->normalizeSlug($slug);

        if ($slug === null) {
            return [
                'message' => 'Accede desde el subdominio de tu restaurante (ej. mi-local.tudominio.com).',
                'code' => self::CODE_MISSING_SUBDOMAIN,
                'status' => 400,
            ];
        }

        if ($this->isReservedSlug($slug)) {
            return [
                'message' => 'Subdominio no válido para la app del restaurante.',
                'code' => self::CODE_RESERVED_SUBDOMAIN,
                'status' => 404,
            ];
        }

        $tenant = Tenant::query()->where('slug', $slug)->first();

        if (! $tenant) {
            return [
                'message' => 'Restaurante no encontrado.',
                'code' => self::CODE_NOT_FOUND,
                'status' => 404,
            ];
        }

        return $this->evaluateTenant($tenant, $autoSuspendOnExpiry);
    }

    public function denyJsonResponse(array $denial): JsonResponse
    {
        return response()->json([
            'message' => $denial['message'],
            'code' => $denial['code'],
        ], $denial['status']);
    }

    /**
     * @return Tenant|null Tenant conectado o null si hubo denegación (respuesta ya enviada al caller vía referencia).
     */
    public function connectAccessibleTenant(string $slug, bool $autoSuspendOnExpiry = true): Tenant|array
    {
        $result = $this->resolveAccessibleTenant($slug, $autoSuspendOnExpiry);

        if (! isset($result['tenant'])) {
            return $result;
        }

        TenantConnectionManager::connect($result['tenant']);

        return $result['tenant'];
    }
}
