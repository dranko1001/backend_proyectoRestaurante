<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CocinaLlamadaMesero extends Model
{
    protected $table = 'cocina_llamada_mesero';

    public $timestamps = false;

    protected $fillable = [
        'cocinero_idUsuario',
        'cajero_idUsuario',
        'creado_en',
        'atendida_en',
        'mesero_idUsuario',
    ];

    protected function casts(): array
    {
        return [
            'creado_en' => 'datetime',
            'atendida_en' => 'datetime',
        ];
    }

    public function cocinero(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cocinero_idUsuario', 'idUsuario');
    }

    public function cajero(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cajero_idUsuario', 'idUsuario');
    }

    public function mesero(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'mesero_idUsuario', 'idUsuario');
    }
}
