<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoEstadoLog;
use App\Services\ProductoActivoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminProductoController extends Controller
{
    public function __construct(
        private readonly ProductoActivoService $productoActivoService,
    ) {}
    public function index(Request $request): JsonResponse
    {
        $verEliminados = $request->boolean('eliminados');

        $productos = Producto::query()
            ->with(['categoria:idCategoria,nombre,orden,activa'])
            ->join('categoria', 'producto.categoria_idCategoria', '=', 'categoria.idCategoria')
            ->when(
                $verEliminados,
                fn ($q) => $q->whereNotNull('producto.eliminado_en'),
                fn ($q) => $q->whereNull('producto.eliminado_en'),
            )
            ->orderBy('categoria.orden')
            ->orderBy('categoria.nombre')
            ->orderBy('producto.nombreProducto')
            ->select('producto.*')
            ->get();

        $categorias = Categoria::query()
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get(['idCategoria', 'nombre', 'orden', 'activa']);

        $totalEliminados = (int) Producto::query()->whereNotNull('eliminado_en')->count();

        return response()->json([
            'data' => $productos->map(fn (Producto $p) => $this->serializeProducto($p)),
            'categorias' => $categorias,
            'total_eliminados' => $totalEliminados,
        ]);
    }

    public function show(Producto $producto): JsonResponse
    {
        $producto->loadMissing(['categoria:idCategoria,nombre,orden,activa']);

        return response()->json([
            'data' => $this->serializeProducto($producto),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $nombreEliminado = Producto::query()
            ->where('nombreProducto', trim((string) $request->input('nombreProducto')))
            ->where('categoria_idCategoria', $request->input('categoria_idCategoria'))
            ->whereNotNull('eliminado_en')
            ->exists();

        if ($nombreEliminado) {
            return response()->json([
                'message' => 'Ya existe un plato borrado con ese nombre en esa categoría. Restáuralo desde «Platos borrados» o usa otro nombre.',
            ], 422);
        }

        $data = $this->validatePayload($request);
        unset($data['imagen']);

        if ($request->hasFile('imagen')) {
            $data['imagen'] = $request->file('imagen')->store('productos', 'public');
        }

        $producto = Producto::create($data);
        $producto->loadMissing(['categoria:idCategoria,nombre,orden,activa']);

        return response()->json([
            'message' => 'Producto creado.',
            'data' => $this->serializeProducto($producto),
        ], 201);
    }

    public function update(Request $request, Producto $producto): JsonResponse
    {
        $data = $this->validatePayload($request, $producto);
        unset($data['imagen']);

        if ($request->hasFile('imagen')) {
            $this->deleteStoredImage($producto->imagen);
            $data['imagen'] = $request->file('imagen')->store('productos', 'public');
        }

        $producto->fill($data);
        $producto->save();
        $producto->loadMissing(['categoria:idCategoria,nombre,orden,activa']);

        return response()->json([
            'message' => 'Producto actualizado.',
            'data' => $this->serializeProducto($producto),
        ]);
    }

    /**
     * Deshabilitar/habilitar (preferido vs eliminar).
     */
    public function setActivo(Request $request, Producto $producto): JsonResponse
    {
        if ($producto->eliminado_en !== null) {
            return response()->json(['message' => 'Este producto fue eliminado. Restáuralo desde «Platos borrados».'], 422);
        }

        $data = $request->validate([
            'activo' => ['required', 'boolean'],
        ]);

        $userId = (int) $request->user()->getAuthIdentifier();
        $this->productoActivoService->cambiarActivo($producto, (bool) $data['activo'], $userId);

        return response()->json([
            'message' => $producto->activo ? 'Producto habilitado.' : 'Producto deshabilitado.',
            'data' => $this->serializeProducto($producto->fresh(['categoria:idCategoria,nombre,orden,activa'])),
        ]);
    }

    public function historialActivo(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'producto' => ['nullable', 'string', 'max:160'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
        ]);

        $query = ProductoEstadoLog::query()
            ->with([
                'producto:idProducto,nombreProducto',
                'usuario:idUsuario,nombre,apellido,cargos_idCargo',
                'usuario.cargo:idCargo,nombre',
            ])
            ->orderByDesc('creado_en')
            ->limit(250);

        $nombreProducto = trim((string) ($filtros['producto'] ?? ''));
        if ($nombreProducto !== '') {
            $term = '%'.$nombreProducto.'%';
            $query->whereHas('producto', fn ($q) => $q->where('nombreProducto', 'like', $term));
        }

        if (! empty($filtros['fecha_desde'])) {
            $query->whereDate('creado_en', '>=', $filtros['fecha_desde']);
        }

        if (! empty($filtros['fecha_hasta'])) {
            $query->whereDate('creado_en', '<=', $filtros['fecha_hasta']);
        }

        $logs = $query->get();
        $porProductoAsc = ProductoEstadoLog::query()
            ->orderBy('producto_idProducto')
            ->orderBy('creado_en')
            ->get()
            ->groupBy('producto_idProducto');

        $data = $logs->map(function (ProductoEstadoLog $log) use ($porProductoAsc) {
            $periodoFin = null;
            if (! $log->activo) {
                $grupo = $porProductoAsc->get($log->producto_idProducto, collect());
                $periodoFin = $grupo->first(
                    fn (ProductoEstadoLog $otro) => $otro->activo
                        && $otro->creado_en
                        && $log->creado_en
                        && $otro->creado_en->gt($log->creado_en)
                )?->creado_en;
            }

            return [
                'idLog' => $log->idLog,
                'activo' => (bool) $log->activo,
                'accion' => $log->activo ? 'HABILITADO' : 'DESHABILITADO',
                'creado_en' => $log->creado_en?->toIso8601String(),
                'periodo_fin' => $periodoFin?->toIso8601String(),
                'producto' => $log->producto ? [
                    'idProducto' => $log->producto->idProducto,
                    'nombreProducto' => $log->producto->nombreProducto,
                ] : null,
                'usuario' => $log->usuario
                    ? trim($log->usuario->nombre.' '.$log->usuario->apellido)
                    : '—',
                'usuario_rol' => $log->usuario?->cargo?->nombre,
            ];
        });

        return response()->json([
            'total' => $data->count(),
            'data' => $data,
        ]);
    }

    /**
     * Borrado suave: el plato se oculta del menú y del panel, pero se conserva
     * en la base de datos (junto con su imagen e historial de pedidos).
     */
    public function destroy(Producto $producto): JsonResponse
    {
        if ($producto->eliminado_en !== null) {
            return response()->json(['message' => 'Este producto ya fue eliminado.'], 422);
        }

        $producto->eliminado_en = Carbon::now();
        $producto->activo = false;
        $producto->save();

        return response()->json([
            'message' => 'Producto eliminado. Puedes restaurarlo desde «Platos borrados».',
            'data' => $this->serializeProducto($producto->fresh(['categoria:idCategoria,nombre,orden,activa'])),
        ]);
    }

    /**
     * Restaura un plato borrado (vuelve visible y activo).
     */
    public function restaurar(Producto $producto): JsonResponse
    {
        if ($producto->eliminado_en === null) {
            return response()->json(['message' => 'Este producto no está eliminado.'], 422);
        }

        $producto->eliminado_en = null;
        $producto->activo = true;
        $producto->save();

        return response()->json([
            'message' => 'Producto restaurado.',
            'data' => $this->serializeProducto($producto->fresh(['categoria:idCategoria,nombre,orden,activa'])),
        ]);
    }

    private function deleteStoredImage(?string $path): void
    {
        if (! $path) {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function validatePayload(Request $request, ?Producto $producto = null): array
    {
        if ($request->input('receta_idReceta') === '') {
            $request->merge(['receta_idReceta' => null]);
        }

        $categoriaId = $request->input('categoria_idCategoria');

        $data = $request->validate([
            'nombreProducto' => [
                'required',
                'string',
                'max:120',
                Rule::unique('producto', 'nombreProducto')
                    ->where(fn ($q) => $q->where('categoria_idCategoria', $categoriaId))
                    ->ignore($producto?->idProducto, 'idProducto'),
            ],
            'precio' => ['required', 'numeric', 'min:500'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'tipo' => ['required', 'string', 'in:PLATO,BEBIDA,COMBO'],
            'categoria_idCategoria' => ['required', 'integer', Rule::exists('categoria', 'idCategoria')],
            'receta_idReceta' => ['nullable', 'integer', Rule::exists('receta', 'idReceta')],
            'activo' => ['sometimes', 'boolean'],
            'imagen' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:5120'],
        ]);

        return $data;
    }

    private function serializeProducto(Producto $p): array
    {
        $imagenUrl = null;
        if ($p->imagen) {
            $imagenUrl = asset('storage/'.$p->imagen);
        }

        return [
            'idProducto' => $p->idProducto,
            'nombreProducto' => $p->nombreProducto,
            'precio' => $p->precio,
            'descripcion' => $p->descripcion,
            'imagen' => $p->imagen,
            'imagenUrl' => $imagenUrl,
            'tipo' => $p->tipo,
            'activo' => (bool) $p->activo,
            'eliminado_en' => $p->eliminado_en?->toIso8601String(),
            'categoria_idCategoria' => $p->categoria_idCategoria,
            'receta_idReceta' => $p->receta_idReceta,
            'categoria' => $p->categoria ? [
                'idCategoria' => $p->categoria->idCategoria,
                'nombre' => $p->categoria->nombre,
                'orden' => $p->categoria->orden,
                'activa' => (bool) $p->categoria->activa,
            ] : null,
        ];
    }
}
