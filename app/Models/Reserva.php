<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reserva extends Model
{
    protected $table = 'reserva';

    protected $primaryKey = 'idReserva';

    public $timestamps = false;

    protected $fillable = [
        'cliente_idUsuario',
        'mesa_idMesa',
        'fecha_hora',
        'num_personas',
        'estado',
        'notas',
        'motivo_cancelacion',
        'creado_en',
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
            'creado_en' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cliente_idUsuario', 'idUsuario');
    }

    public function mesa(): BelongsTo
    {
        return $this->belongsTo(Mesa::class, 'mesa_idMesa', 'idMesa');
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'reserva_idReserva', 'idReserva');
    }
}
