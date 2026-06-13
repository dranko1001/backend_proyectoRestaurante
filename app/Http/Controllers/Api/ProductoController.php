<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductoController extends Controller
{
    /**
     * Catálogo para clientes autenticados (solo productos y categorías activos).
     */
    public function indexCliente(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tipo' => ['sometimes', 'string', 'in:PLATO,BEBIDA,COMBO'],
        ]);

        $query = Producto::query()
            ->with([
                'categoria:idCategoria,nombre,orden,activa',
            ])
            ->where('producto.activo', true)
            ->whereHas('categoria', fn ($q) => $q->where('activa', true))
            ->join('categoria', 'producto.categoria_idCategoria', '=', 'categoria.idCategoria')
            ->orderBy('categoria.orden')
            ->orderBy('categoria.nombre')
            ->orderBy('producto.nombreProducto')
            ->select('producto.*');

        if (! empty($validated['tipo'])) {
            $query->where('producto.tipo', $validated['tipo']);
        }

        $productos = $query->get();

        return response()->json([
            'data' => $productos->map(fn (Producto $p) => [
                'idProducto' => $p->idProducto,
                'nombreProducto' => $p->nombreProducto,
                'precio' => $p->precio,
                'descripcion' => $p->descripcion,
                'tipo' => $p->tipo,
                'categoria' => $p->categoria ? [
                    'idCategoria' => $p->categoria->idCategoria,
                    'nombre' => $p->categoria->nombre,
                    'orden' => $p->categoria->orden,
                ] : null,
            ]),
        ]);
    }
}
