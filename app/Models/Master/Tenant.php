<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $connection = 'master';

    protected $fillable = [
        'slug',
        'db_name',
        'contact_email',
        'nombre_comercial',
        'status',
        'provision_error',
        'provisioned_at',
        'onboarding_completed_at',
        'access_expires_at',
        'access_cancel_at_period_end',
        'license_months',
    ];

    protected function casts(): array
    {
        return [
            'provisioned_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'access_expires_at' => 'datetime',
            'access_cancel_at_period_end' => 'boolean',
        ];
    }

    public function isAccessActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->access_expires_at === null) {
            return true;
        }

        return $this->access_expires_at->isFuture();
    }

    public function accessDaysRemaining(): ?int
    {
        if ($this->access_expires_at === null) {
            return null;
        }

        if ($this->access_expires_at->isPast()) {
            return 0;
        }

        return (int) now()->diffInDays($this->access_expires_at, false);
    }

    public function extendAccessByMonths(int $months): void
    {
        $base = ($this->access_expires_at && $this->access_expires_at->isFuture())
            ? $this->access_expires_at->copy()
            : now();

        $this->access_expires_at = $base->addMonths($months);
        $this->access_cancel_at_period_end = false;

        if ($this->status === 'suspended') {
            $this->status = 'active';
        }

        $this->save();
    }

    public function scheduleAccessCancellationAtPeriodEnd(): bool
    {
        if ($this->access_expires_at && $this->access_expires_at->isFuture()) {
            $this->access_cancel_at_period_end = true;
            $this->save();

            return true;
        }

        $this->update([
            'status' => 'suspended',
            'access_cancel_at_period_end' => false,
        ]);

        return false;
    }

    public function isAccessScheduledForCancellation(): bool
    {
        return (bool) $this->access_cancel_at_period_end
            && $this->status === 'active'
            && $this->access_expires_at
            && $this->access_expires_at->isFuture();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(OnboardingInvitation::class, 'tenant_id');
    }

    public function tenantAppUrl(): string
    {
        return \App\Support\Tenancy\TenantUrl::appForSlug($this->slug);
    }
}
