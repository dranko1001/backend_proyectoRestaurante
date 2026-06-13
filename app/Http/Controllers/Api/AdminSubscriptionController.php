<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Master\PlatformBillingSetting;
use App\Models\Master\SubscriptionRenewalRequest;
use App\Support\PublicStorage;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminSubscriptionController extends Controller
{
    private const MONTH_OPTIONS = [1, 3, 6, 12];

    public function show(): JsonResponse
    {
        if (! TenantContext::isMulti()) {
            return response()->json([
                'data' => [
                    'multi_tenant' => false,
                    'renewal_available' => false,
                ],
            ]);
        }

        $tenant = TenantContext::current();
        if (! $tenant) {
            return response()->json([
                'data' => [
                    'multi_tenant' => true,
                    'renewal_available' => false,
                ],
            ]);
        }

        $tenant->refresh();
        $billing = PlatformBillingSetting::current();
        $pending = SubscriptionRenewalRequest::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', SubscriptionRenewalRequest::STATUS_PENDING)
            ->latest('id')
            ->first();

        $packages = $billing->packagePrices();

        return response()->json([
            'data' => [
                'multi_tenant' => true,
                'renewal_available' => true,
                'license' => [
                    'access_expires_at' => $tenant->access_expires_at?->toIso8601String(),
                    'days_remaining' => $tenant->accessDaysRemaining(),
                    'scheduled_cancellation' => $tenant->isAccessScheduledForCancellation(),
                    'status' => $tenant->status,
                    'access_active' => $tenant->isAccessActive(),
                ],
                'billing' => [
                    'nequi_key' => $billing->nequi_key,
                    'nequi_qr_url' => PublicStorage::publicUrl($billing->nequi_qr_path),
                    'payment_instructions' => $billing->payment_instructions,
                    'package_prices' => [
                        '1' => $packages[1],
                        '3' => $packages[3],
                        '6' => $packages[6],
                        '12' => $packages[12],
                    ],
                    'month_options' => PlatformBillingSetting::PACKAGE_MONTHS,
                    'configured' => $billing->nequi_key !== null
                        || $billing->nequi_qr_path !== null
                        || collect($packages)->contains(fn (int $price) => $price > 0),
                ],
                'pending_request' => $pending ? $this->serializePendingRequest($pending) : null,
            ],
        ]);
    }

    public function storeRenewal(Request $request): JsonResponse
    {
        if (! TenantContext::isMulti()) {
            throw ValidationException::withMessages([
                'tenant' => ['La renovación por Nequi solo está disponible en modo multi-tenant.'],
            ]);
        }

        $tenant = TenantContext::current();
        if (! $tenant) {
            throw ValidationException::withMessages([
                'tenant' => ['No se pudo identificar el restaurante.'],
            ]);
        }

        $data = $request->validate([
            'months' => ['required', 'integer', 'in:'.implode(',', self::MONTH_OPTIONS)],
            'payment_reference' => ['required', 'string', 'min:3', 'max:120'],
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        $hasPending = SubscriptionRenewalRequest::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', SubscriptionRenewalRequest::STATUS_PENDING)
            ->exists();

        if ($hasPending) {
            throw ValidationException::withMessages([
                'renewal' => ['Ya tienes una solicitud de renovación pendiente de revisión.'],
            ]);
        }

        $billing = PlatformBillingSetting::current();
        $months = (int) $data['months'];
        $amountCop = $billing->priceForMonths($months);

        $renewal = SubscriptionRenewalRequest::query()->create([
            'tenant_id' => $tenant->id,
            'months' => $months,
            'amount_cop' => $amountCop,
            'payment_reference' => trim($data['payment_reference']),
            'status' => SubscriptionRenewalRequest::STATUS_PENDING,
            'admin_note' => $data['admin_note'] ?? null,
        ]);

        return response()->json([
            'message' => 'Solicitud enviada. El proveedor revisará tu pago y extenderá la licencia.',
            'data' => $this->serializePendingRequest($renewal),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePendingRequest(SubscriptionRenewalRequest $row): array
    {
        return [
            'id' => $row->id,
            'months' => $row->months,
            'amount_cop' => $row->amount_cop,
            'payment_reference' => $row->payment_reference,
            'status' => $row->status,
            'admin_note' => $row->admin_note,
            'created_at' => $row->created_at?->toIso8601String(),
        ];
    }
}
