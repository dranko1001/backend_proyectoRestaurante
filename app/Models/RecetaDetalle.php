<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecetaDetalle extends Model
{
    protected $table = 'recetadetalle';

    protected $primaryKey = 'idDetalle';

    public $timestamps = false;

    protected $fillable = [
        'cantidad',
        'receta_idReceta',
        'ingrediente_idIngrediente',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
        ];
    }

    public function receta(): BelongsTo
    {
        return $this->belongsTo(Receta::class, 'receta_idReceta', 'idReceta');
    }

    public function ingrediente(): BelongsTo
    {
        return $this->belongsTo(Ingrediente::class, 'ingrediente_idIngrediente', 'idIngrediente');
    }
}
