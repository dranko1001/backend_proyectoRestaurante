<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Receta extends Model
{
    protected $table = 'receta';

    protected $primaryKey = 'idReceta';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'rendimiento',
    ];

    public function detalles(): HasMany
    {
        return $this->hasMany(RecetaDetalle::class, 'receta_idReceta', 'idReceta');
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'receta_idReceta', 'idReceta');
    }
}
