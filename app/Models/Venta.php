<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venta extends Model
{
    protected $table = 'venta';

    protected $primaryKey = 'idVenta';

    public $timestamps = false;

    protected $fillable = [
        'pedido_idPedido',
        'subtotal',
        'impuesto_o_servicio',
        'total',
        'registrada_en',
        'cajero_idUsuario',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'impuesto_o_servicio' => 'decimal:2',
            'total' => 'decimal:2',
            'registrada_en' => 'datetime',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_idPedido', 'idPedido');
    }

    public function cajero(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cajero_idUsuario', 'idUsuario');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'venta_idVenta', 'idVenta');
    }
}
