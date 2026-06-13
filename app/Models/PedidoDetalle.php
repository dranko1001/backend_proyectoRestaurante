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
        'creado_en',
    ];

    protected function casts(): array
    {
        return [
            'precio_unitario' => 'decimal:2',
            'creado_en' => 'datetime',
        ];
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
