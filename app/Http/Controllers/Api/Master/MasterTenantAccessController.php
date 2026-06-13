<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MasterTenantAccessController extends Controller
{
    public function suspend(Tenant $tenant): JsonResponse
    {
        if ($tenant->status !== 'active') {
            throw ValidationException::withMessages([
                'tenant' => ['Solo puedes desactivar restaurantes que estén activos.'],
            ]);
        }

        if ($tenant->isAccessScheduledForCancellation()) {
            return response()->json([
                'message' => 'El acceso ya está programado para finalizar al vencer la licencia actual.',
                'data' => $this->serializeTenant($tenant),
            ]);
        }

        $scheduled = $tenant->scheduleAccessCancellationAtPeriodEnd();

        if ($scheduled) {
            $tenant->refresh();
            $fecha = $tenant->access_expires_at?->timezone(config('app.timezone'))->format('d/m/Y');

            return response()->json([
                'message' => "Acceso programado para finalizar el {$fecha}. El cliente puede seguir usando el sistema hasta esa fecha.",
                'data' => $this->serializeTenant($tenant),
            ]);
        }

        return response()->json([
            'message' => 'Acceso desactivado. El cliente ya no puede entrar a su subdominio.',
            'data' => $this->serializeTenant($tenant->fresh()),
        ]);
    }

    public function extendAccess(Request $request, Tenant $tenant): JsonResponse
    {
        if (! in_array($tenant->status, ['active', 'suspended'], true)) {
            throw ValidationException::withMessages([
                'tenant' => ['Solo puedes asignar meses a restaurantes activos o suspendidos.'],
            ]);
        }

        if ($tenant->onboarding_completed_at === null) {
            throw ValidationException::withMessages([
                'tenant' => ['Este restaurante aún no completó el onboarding.'],
            ]);
        }

        $data = $request->validate([
            'months' => ['required', 'integer', 'min:1', 'max:36'],
        ]);

        $wasSuspended = $tenant->status === 'suspended';
        $wasCancelled = (bool) $tenant->access_cancel_at_period_end;

        $tenant->extendAccessByMonths((int) $data['months']);
        $tenant->refresh();

        $message = "Acceso extendido {$data['months']} mes(es).";
        if ($wasSuspended || $wasCancelled) {
            $message .= ' Suscripción reactivada por el Master.';
        }

        return response()->json([
            'message' => $message,
            'data' => $this->serializeTenant($tenant),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTenant(Tenant $tenant): array
    {
        return [
            'id' => $tenant->id,
            'slug' => $tenant->slug,
            'status' => $tenant->status,
            'access_expires_at' => $tenant->access_expires_at?->toIso8601String(),
            'access_cancel_at_period_end' => (bool) $tenant->access_cancel_at_period_end,
            'access_scheduled_cancellation' => $tenant->isAccessScheduledForCancellation(),
            'access_active' => $tenant->isAccessActive(),
            'access_days_remaining' => $tenant->accessDaysRemaining(),
        ];
    }
}
