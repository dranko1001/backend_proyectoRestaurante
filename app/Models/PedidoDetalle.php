<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoDetalle extends Model
{
    protected $table = 'pedido_detalle';

    protected $primaryKey = 'idPedidoDetalle';

    public $timestamps = false;

    protected $fillable = [
        'pedido_idPedido',
        'producto_idProducto',
        'cantidad',
        'precio_unitario',
        'nota',
        'estado_item',
        'motivo_cancelacion',
        'cancelado_en',
        'cancelado_por_idUsuario',
        'creado_en',
    ];

    protected function casts(): array
    {
        return [
            'precio_unitario' => 'decimal:2',
            'creado_en' => 'datetime',
            'cancelado_en' => 'datetime',
        ];
    }

    public function canceladoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cancelado_por_idUsuario', 'idUsuario');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_idPedido', 'idPedido');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_idProducto', 'idProducto');
    }
}
