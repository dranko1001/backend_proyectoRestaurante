<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionRenewalRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $connection = 'master';

    protected $fillable = [
        'tenant_id',
        'months',
        'amount_cop',
        'payment_reference',
        'status',
        'reviewed_by',
        'reviewed_at',
        'admin_note',
        'master_note',
    ];

    protected function casts(): array
    {
        return [
            'months' => 'integer',
            'amount_cop' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(MasterUser::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
