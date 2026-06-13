<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Pedido extends Model
{
    protected $table = 'pedido';

    protected $primaryKey = 'idPedido';

    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'mesa_idMesa',
        'mesero_idUsuario',
        'reserva_idReserva',
        'estado',
        'notas',
        'creado_en',
        'actualizado_en',
        'cerrado_en',
    ];

    protected function casts(): array
    {
        return [
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
            'cerrado_en' => 'datetime',
        ];
    }

    public function mesa(): BelongsTo
    {
        return $this->belongsTo(Mesa::class, 'mesa_idMesa', 'idMesa');
    }

    public function mesero(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'mesero_idUsuario', 'idUsuario');
    }

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class, 'reserva_idReserva', 'idReserva');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(PedidoDetalle::class, 'pedido_idPedido', 'idPedido');
    }

    public function venta(): HasOne
    {
        return $this->hasOne(Venta::class, 'pedido_idPedido', 'idPedido');
    }
}
