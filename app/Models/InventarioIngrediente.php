<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioIngrediente extends Model
{
    protected $table = 'inventarioingrediente';

    protected $primaryKey = 'ingrediente_idIngrediente';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'ingrediente_idIngrediente',
        'stock',
        'stock_minimo',
        'actualizado_en',
    ];

    protected function casts(): array
    {
        return [
            'stock' => 'decimal:4',
            'stock_minimo' => 'decimal:4',
            'actualizado_en' => 'datetime',
        ];
    }

    public function ingrediente(): BelongsTo
    {
        return $this->belongsTo(Ingrediente::class, 'ingrediente_idIngrediente', 'idIngrediente');
    }
}
