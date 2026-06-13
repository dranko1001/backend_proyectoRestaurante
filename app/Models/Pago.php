<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pago extends Model
{
    protected $table = 'pago';

    protected $primaryKey = 'idPago';

    public $timestamps = false;

    protected $fillable = [
        'venta_idVenta',
        'metodo',
        'valor',
        'referencia',
        'pagado_en',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'pagado_en' => 'datetime',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_idVenta', 'idVenta');
    }
}
