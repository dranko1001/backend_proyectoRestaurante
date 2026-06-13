<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

class AdminLicenseController extends Controller
{
    public function status(): JsonResponse
    {
        if (! TenantContext::isMulti()) {
            return response()->json([
                'data' => [
                    'show_warning' => false,
                    'multi_tenant' => false,
                ],
            ]);
        }

        $tenant = TenantContext::current();
        if (! $tenant) {
            return response()->json([
                'data' => [
                    'show_warning' => false,
                    'multi_tenant' => true,
                ],
            ]);
        }

        $tenant->refresh();

        $days = $tenant->accessDaysRemaining();
        $warningDays = (int) config('tenancy.license_warning_days', 7);
        $hasExpiry = $tenant->access_expires_at !== null;
        $active = $tenant->isAccessActive();
        $scheduled = $tenant->isAccessScheduledForCancellation();

        $showWarning = $hasExpiry
            && $active
            && $days !== null
            && $days <= $warningDays;

        return response()->json([
            'data' => [
                'multi_tenant' => true,
                'access_expires_at' => $tenant->access_expires_at?->toIso8601String(),
                'days_remaining' => $days,
                'warning_days' => $warningDays,
                'show_warning' => $showWarning,
                'scheduled_cancellation' => $scheduled,
                'license_months' => $tenant->license_months,
                'message' => $this->buildWarningMessage($tenant, $days, $scheduled, $showWarning),
            ],
        ]);
    }

    private function buildWarningMessage($tenant, ?int $days, bool $scheduled, bool $showWarning): ?string
    {
        if (! $showWarning || $days === null) {
            return null;
        }

        $fecha = $tenant->access_expires_at?->timezone(config('app.timezone'))->format('d/m/Y');

        if ($scheduled) {
            return "Tu suscripción está programada para finalizar el {$fecha} ({$days} día(s) restantes). Contacta al proveedor para renovar.";
        }

        if ($days <= 0) {
            return 'Tu licencia vence hoy. Contacta al proveedor para renovar el acceso.';
        }

        return "Tu licencia vence el {$fecha} ({$days} día(s) restantes). Contacta al proveedor para renovar.";
    }
}
