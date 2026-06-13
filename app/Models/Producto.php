<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    protected $table = 'producto';

    protected $primaryKey = 'idProducto';

    public $timestamps = false;

    protected $fillable = [
        'nombreProducto',
        'precio',
        'descripcion',
        'tipo',
        'categoria_idCategoria',
        'receta_idReceta',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_idCategoria', 'idCategoria');
    }

    public function receta(): BelongsTo
    {
        return $this->belongsTo(Receta::class, 'receta_idReceta', 'idReceta');
    }

    public function pedidoDetalles(): HasMany
    {
        return $this->hasMany(PedidoDetalle::class, 'producto_idProducto', 'idProducto');
    }
}
