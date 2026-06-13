<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoEstadoLog extends Model
{
    protected $table = 'producto_estado_log';

    protected $primaryKey = 'idLog';

    public $timestamps = false;

    protected $fillable = [
        'producto_idProducto',
        'activo',
        'usuario_idUsuario',
        'creado_en',
        'atendida_en',
        'mesero_atendio_idUsuario',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'creado_en' => 'datetime',
            'atendida_en' => 'datetime',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_idProducto', 'idProducto');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_idUsuario', 'idUsuario');
    }

    public function meseroAtendio(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'mesero_atendio_idUsuario', 'idUsuario');
    }
}
