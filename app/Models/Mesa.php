<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mesa extends Model
{
    protected $table = 'mesa';

    protected $primaryKey = 'idMesa';

    public $timestamps = false;

    protected $fillable = [
        'numero',
        'nombre',
        'capacidad',
        'estado',
        'activa',
        'eliminada_en',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
            'eliminada_en' => 'datetime',
        ];
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'mesa_idMesa', 'idMesa');
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class, 'mesa_idMesa', 'idMesa');
    }
}
