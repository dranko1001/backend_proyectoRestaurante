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
        'numero_factura',
        'subtotal',
        'impuesto_o_servicio',
        'total',
        'recibido',
        'cambio',
        'registrada_en',
        'cajero_idUsuario',
        'estado',
        'motivo_cancelacion',
        'cancelada_en',
        'cancelada_por_idUsuario',
        'admin_visto',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'impuesto_o_servicio' => 'decimal:2',
            'total' => 'decimal:2',
            'recibido' => 'decimal:2',
            'cambio' => 'decimal:2',
            'registrada_en' => 'datetime',
            'cancelada_en' => 'datetime',
            'admin_visto' => 'boolean',
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

    public function canceladaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cancelada_por_idUsuario', 'idUsuario');
    }

    public function scopeActivas($query)
    {
        return $query->where(function ($q) {
            $q->where('estado', 'ACTIVA')->orWhereNull('estado');
        });
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'venta_idVenta', 'idVenta');
    }
}
