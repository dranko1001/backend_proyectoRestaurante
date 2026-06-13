<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductoController extends Controller
{
    /**
     * Mismo catálogo para visitantes (sin sesión): solo datos ya visibles para clientes registrados (activos).
     */
    public function catalogoPublico(Request $request): JsonResponse
    {
        return $this->catalogoActivo($request);
    }

    /**
     * Mismo catálogo para meseros al registrar pedidos en salón.
     */
    public function indexMesero(Request $request): JsonResponse
    {
        return $this->catalogoActivo($request);
    }

    /**
     * Categorías activas para filtros del menú en salón (sin productos).
     */
    public function categoriasMesero(Request $request): JsonResponse
    {
        $categorias = Categoria::query()
            ->where('activa', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get(['idCategoria', 'nombre', 'orden']);

        return response()->json([
            'data' => $categorias->map(fn (Categoria $c) => [
                'idCategoria' => $c->idCategoria,
                'nombre' => $c->nombre,
                'orden' => $c->orden,
            ]),
        ]);
    }

    private function catalogoActivo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tipo' => ['sometimes', 'string', 'in:PLATO,BEBIDA,COMBO'],
            'categoria_id' => ['sometimes', 'integer', 'exists:categoria,idCategoria'],
            'q' => ['sometimes', 'string', 'min:2', 'max:80'],
        ]);

        $query = Producto::query()
            ->with([
                'categoria:idCategoria,nombre,orden,activa',
            ])
            ->where('producto.activo', true)
            ->whereNull('producto.eliminado_en')
            ->whereHas('categoria', fn ($q) => $q->where('activa', true))
            ->join('categoria', 'producto.categoria_idCategoria', '=', 'categoria.idCategoria')
            ->orderBy('categoria.orden')
            ->orderBy('categoria.nombre')
            ->orderBy('producto.nombreProducto')
            ->select('producto.*');

        if (! empty($validated['tipo'])) {
            $query->where('producto.tipo', $validated['tipo']);
        }

        if (! empty($validated['categoria_id'])) {
            $query->where('producto.categoria_idCategoria', $validated['categoria_id']);
        }

        if (! empty($validated['q'])) {
            $term = '%'.addcslashes($validated['q'], '%_\\').'%';
            $query->where(function ($q) use ($term) {
                $q->where('producto.nombreProducto', 'like', $term)
                    ->orWhere('producto.descripcion', 'like', $term);
            });
        }

        $productos = $query->get();

        return response()->json([
            'data' => $productos->map(function (Producto $p) {
                $imagenUrl = $p->imagen ? asset('storage/'.$p->imagen) : null;

                return [
                    'idProducto' => $p->idProducto,
                    'nombreProducto' => $p->nombreProducto,
                    'precio' => $p->precio,
                    'descripcion' => $p->descripcion,
                    'imagenUrl' => $imagenUrl,
                    'tipo' => $p->tipo,
                    'categoria' => $p->categoria ? [
                        'idCategoria' => $p->categoria->idCategoria,
                        'nombre' => $p->categoria->nombre,
                        'orden' => $p->categoria->orden,
                    ] : null,
                ];
            }),
        ]);
    }
}
