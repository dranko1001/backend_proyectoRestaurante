<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Ingrediente extends Model
{
    protected $table = 'ingrediente';

    protected $primaryKey = 'idIngrediente';

    public $timestamps = false;

    protected $fillable = [
        'nombreIngrediente',
        'unidad',
    ];

    public function inventario(): HasOne
    {
        return $this->hasOne(InventarioIngrediente::class, 'ingrediente_idIngrediente', 'idIngrediente');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoIngrediente::class, 'ingrediente_idIngrediente', 'idIngrediente');
    }

    public function recetaDetalles(): HasMany
    {
        return $this->hasMany(RecetaDetalle::class, 'ingrediente_idIngrediente', 'idIngrediente');
    }
}
