<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Services\ProductoActivoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CocinaProductoController extends Controller
{
    public function __construct(
        private readonly ProductoActivoService $productoActivoService,
    ) {}

    public function index(): JsonResponse
    {
        $productos = Producto::query()
            ->with(['categoria:idCategoria,nombre,orden,activa'])
            ->join('categoria', 'producto.categoria_idCategoria', '=', 'categoria.idCategoria')
            ->whereNull('producto.eliminado_en')
            ->orderBy('categoria.orden')
            ->orderBy('categoria.nombre')
            ->orderBy('producto.nombreProducto')
            ->select('producto.*')
            ->get();

        return response()->json([
            'total' => $productos->count(),
            'data' => $productos->map(fn (Producto $p) => $this->serialize($p)),
        ]);
    }

    public function setActivo(Request $request, Producto $producto): JsonResponse
    {
        if ($producto->eliminado_en !== null) {
            return response()->json(['message' => 'Este plato fue eliminado del menú.'], 422);
        }

        $data = $request->validate([
            'activo' => ['required', 'boolean'],
        ]);

        $userId = (int) $request->user()->getAuthIdentifier();
        $this->productoActivoService->cambiarActivo($producto, (bool) $data['activo'], $userId);

        $producto->loadMissing(['categoria:idCategoria,nombre,orden,activa']);

        return response()->json([
            'message' => $producto->activo ? 'Plato habilitado en el menú.' : 'Plato deshabilitado en el menú.',
            'data' => $this->serialize($producto->fresh(['categoria:idCategoria,nombre,orden,activa'])),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Producto $p): array
    {
        $imagenUrl = $p->imagen ? asset('storage/'.$p->imagen) : null;

        return [
            'idProducto' => $p->idProducto,
            'nombreProducto' => $p->nombreProducto,
            'precio' => $p->precio,
            'descripcion' => $p->descripcion,
            'imagenUrl' => $imagenUrl,
            'tipo' => $p->tipo,
            'activo' => (bool) $p->activo,
            'categoria_idCategoria' => $p->categoria_idCategoria,
            'categoria' => $p->categoria ? [
                'idCategoria' => $p->categoria->idCategoria,
                'nombre' => $p->categoria->nombre,
                'orden' => $p->categoria->orden,
            ] : null,
        ];
    }
}
