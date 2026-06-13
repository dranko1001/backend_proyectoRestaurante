<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\OnboardingInvitation;
use App\Models\Master\Tenant;
use App\Services\Tenancy\OnboardingInvitationMailer;
use App\Support\Tenancy\TenantSlug;
use App\Support\Tenancy\TenantUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MasterInvitationController extends Controller
{
    public function index(): JsonResponse
    {
        $tenants = Tenant::query()
            ->with(['invitations' => fn ($q) => $q->latest()->limit(1)])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $tenants->map(fn (Tenant $t) => [
                'id' => $t->id,
                'slug' => $t->slug,
                'contact_email' => $t->contact_email,
                'nombre_comercial' => $t->nombre_comercial,
                'status' => $t->status,
                'provision_error' => $t->provision_error,
                'tenant_url' => $t->isAccessActive() ? $t->tenantAppUrl() : null,
                'admin_login' => $t->isAccessActive() ? $t->tenantAppUrl().'/staff?rol=admin' : null,
                'cliente_url' => $t->isAccessActive() ? $t->tenantAppUrl().'/cliente' : null,
                'created_at' => $t->created_at?->toIso8601String(),
                'onboarding_completed_at' => $t->onboarding_completed_at?->toIso8601String(),
                'access_expires_at' => $t->access_expires_at?->toIso8601String(),
                'access_cancel_at_period_end' => (bool) $t->access_cancel_at_period_end,
                'access_scheduled_cancellation' => $t->isAccessScheduledForCancellation(),
                'access_active' => $t->isAccessActive(),
                'access_days_remaining' => $t->accessDaysRemaining(),
                'license_months' => $t->license_months,
                'last_invitation' => $t->invitations->first() ? [
                    'email' => $t->invitations->first()->email,
                    'expires_at' => $t->invitations->first()->expires_at->toIso8601String(),
                    'used_at' => $t->invitations->first()->used_at?->toIso8601String(),
                ] : null,
            ]),
        ]);
    }

    public function store(Request $request, OnboardingInvitationMailer $mailer): JsonResponse
    {
        $rawSlug = (string) $request->input('slug', '');
        $slugError = TenantSlug::validationMessage($rawSlug);
        if ($slugError !== null) {
            throw ValidationException::withMessages(['slug' => [$slugError]]);
        }

        $normalizedSlug = TenantSlug::normalize($rawSlug);
        $request->merge(['slug' => $normalizedSlug]);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'slug' => [
                'required',
                'string',
                'min:2',
                'max:40',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique(Tenant::class, 'slug'),
                Rule::notIn(array_merge(
                    [(string) config('tenancy.master_subdomain', 'master')],
                    config('tenancy.reserved_subdomains', [])
                )),
            ],
            'license_months' => ['required', 'integer', 'min:1', 'max:36'],
        ], [
            'slug.regex' => 'El subdominio solo puede tener letras, números y guiones (la ñ se guarda como n).',
            'slug.unique' => 'Ese subdominio ya está en uso. Elige otro nombre.',
            'slug.not_in' => 'Ese subdominio está reservado. Elige otro nombre.',
        ]);

        $plainToken = Str::random(48);
        $ttlHours = (int) config('tenancy.onboarding_token_ttl_hours', 72);

        $result = DB::connection('master')->transaction(function () use ($data, $request, $plainToken, $ttlHours) {
            $dbName = (string) config('tenancy.database_prefix', 'rest_').str_replace('-', '_', $data['slug']);

            $tenant = Tenant::query()->create([
                'slug' => $data['slug'],
                'db_name' => $dbName,
                'contact_email' => $data['email'],
                'status' => 'pending',
                'license_months' => (int) $data['license_months'],
            ]);

            $invitation = OnboardingInvitation::query()->create([
                'tenant_id' => $tenant->id,
                'email' => $data['email'],
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => now()->addHours($ttlHours),
                'created_by' => $request->user()?->id,
            ]);

            return [$tenant, $invitation];
        });

        [$tenant, $invitation] = $result;

        $onboardingUrl = TenantUrl::onboarding($plainToken);
        $emailResult = $mailer->send($tenant, $invitation, $plainToken);

        $message = $emailResult['sent']
            ? 'Invitación creada y correo enviado al cliente.'
            : ($mailer->isConfigured()
                ? 'Invitación creada. No se pudo enviar el correo — copia el enlace de abajo.'
                : 'Invitación creada. Configura SMTP en .env o copia el enlace de abajo.');

        return response()->json([
            'message' => $message,
            'data' => [
                'tenant_id' => $tenant->id,
                'slug' => $tenant->slug,
                'onboarding_url' => $onboardingUrl,
                'subdomain_preview' => $tenant->slug.'.'.TenantUrl::baseDomain(),
                'license_months' => $tenant->license_months,
                'email_sent' => $emailResult['sent'],
                'email_error' => $emailResult['error'],
            ],
        ], 201);
    }

    public function resend(Request $request, Tenant $tenant, OnboardingInvitationMailer $mailer): JsonResponse
    {
        if ($tenant->status === 'active') {
            throw ValidationException::withMessages([
                'tenant' => ['Este restaurante ya está activo.'],
            ]);
        }

        if (in_array($tenant->status, ['failed', 'provisioning'], true)) {
            $tenant->update(['status' => 'pending', 'provision_error' => null]);
        }

        $plainToken = Str::random(48);
        $ttlHours = (int) config('tenancy.onboarding_token_ttl_hours', 72);

        $invitation = DB::connection('master')->transaction(function () use ($tenant, $request, $plainToken, $ttlHours) {
            OnboardingInvitation::query()
                ->where('tenant_id', $tenant->id)
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            return OnboardingInvitation::query()->create([
                'tenant_id' => $tenant->id,
                'email' => $tenant->contact_email,
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => now()->addHours($ttlHours),
                'created_by' => $request->user()?->id,
            ]);
        });

        $onboardingUrl = TenantUrl::onboarding($plainToken);
        $emailResult = $mailer->send($tenant, $invitation, $plainToken);

        $message = $emailResult['sent']
            ? 'Nuevo enlace generado y correo reenviado.'
            : 'Nuevo enlace generado. No se pudo enviar el correo — copia el enlace de abajo.';

        return response()->json([
            'message' => $message,
            'data' => [
                'onboarding_url' => $onboardingUrl,
                'email_sent' => $emailResult['sent'],
                'email_error' => $emailResult['error'],
            ],
        ]);
    }
}
