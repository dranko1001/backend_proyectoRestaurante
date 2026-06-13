<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Hidden(['password'])]
class Usuario extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'usuario';

    protected $primaryKey = 'idUsuario';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'apellido',
        'cedula',
        'telefono',
        'correo',
        'password',
        'cargos_idCargo',
        'activo',
        'creado_en',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'activo' => 'boolean',
            'creado_en' => 'datetime',
        ];
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class, 'cargos_idCargo', 'idCargo');
    }

    public function gastosRegistrados(): HasMany
    {
        return $this->hasMany(Gasto::class, 'registrado_por_idUsuario', 'idUsuario');
    }

    public function movimientosIngrediente(): HasMany
    {
        return $this->hasMany(MovimientoIngrediente::class, 'usuario_idUsuario', 'idUsuario');
    }

    public function pedidosComoMesero(): HasMany
    {
        return $this->hasMany(Pedido::class, 'mesero_idUsuario', 'idUsuario');
    }

    public function reservasComoCliente(): HasMany
    {
        return $this->hasMany(Reserva::class, 'cliente_idUsuario', 'idUsuario');
    }

    public function ventasComoCajero(): HasMany
    {
        return $this->hasMany(Venta::class, 'cajero_idUsuario', 'idUsuario');
    }
}
