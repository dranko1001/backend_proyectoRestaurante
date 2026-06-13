<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\ProductoEstadoLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductoActivoService
{
    public function cambiarActivo(Producto $producto, bool $activo, int $usuarioId): ProductoEstadoLog
    {
        if ((bool) $producto->activo === $activo) {
            throw ValidationException::withMessages([
                'activo' => ['El producto ya está en ese estado.'],
            ]);
        }

        return DB::transaction(function () use ($producto, $activo, $usuarioId) {
            $producto->activo = $activo;
            $producto->save();

            return ProductoEstadoLog::create([
                'producto_idProducto' => $producto->idProducto,
                'activo' => $activo,
                'usuario_idUsuario' => $usuarioId,
                'creado_en' => now(),
            ]);
        });
    }
}
