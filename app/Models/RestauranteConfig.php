<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestauranteConfig extends Model
{
    protected $table = 'restaurante_config';

    protected $primaryKey = 'idConfig';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'idConfig',
        'nombre_comercial',
        'nit_o_documento',
        'telefono',
        'direccion',
        'logo_url',
        'actualizado_en',
    ];

    protected function casts(): array
    {
        return [
            'actualizado_en' => 'datetime',
        ];
    }
}
