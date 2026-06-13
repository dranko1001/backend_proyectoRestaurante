<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoIngrediente extends Model
{
    protected $table = 'movimientoingrediente';

    protected $primaryKey = 'idMovimiento';

    public $timestamps = false;

    protected $fillable = [
        'tipo',
        'cantidad',
        'motivo',
        'referencia',
        'fecha',
        'ingrediente_idIngrediente',
        'usuario_idUsuario',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'fecha' => 'datetime',
        ];
    }

    public function ingrediente(): BelongsTo
    {
        return $this->belongsTo(Ingrediente::class, 'ingrediente_idIngrediente', 'idIngrediente');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_idUsuario', 'idUsuario');
    }
}
