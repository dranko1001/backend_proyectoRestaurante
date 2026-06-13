<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Gasto extends Model
{
    protected $table = 'gasto';

    protected $primaryKey = 'idGasto';

    public $timestamps = false;

    protected $fillable = [
        'categoria',
        'descripcion',
        'valor',
        'fecha',
        'metodo',
        'registrado_por_idUsuario',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'fecha' => 'datetime',
        ];
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'registrado_por_idUsuario', 'idUsuario');
    }
}
