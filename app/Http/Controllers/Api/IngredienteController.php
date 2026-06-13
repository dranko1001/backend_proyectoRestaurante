<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ingrediente;
use App\Models\InventarioIngrediente;
use App\Models\MovimientoIngrediente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class IngredienteController extends Controller
{
    // ──────────────────────────────────────────────────────
    //  HU16 — Listado completo de ingredientes + stock
    //  GET /admin/inventario/ingredientes
    // ──────────────────────────────────────────────────────
    public function index(): JsonResponse
    {
        $ingredientes = Ingrediente::query()
            ->with('inventario')
            ->orderBy('nombreIngrediente')
            ->get()
            ->map(fn(Ingrediente $i) => $this->serialize($i));

        // Contadores para el panel de alertas (HU17)
        $totalBajoStock = $ingredientes->filter(fn($i) => $i['alerta_stock'])->count();

        return response()->json([
            'total' => $ingredientes->count(),
            'total_bajo_stock' => $totalBajoStock,
            'data' => $ingredientes,
        ]);
    }

    // ──────────────────────────────────────────────────────
    //  HU16 — Crear ingrediente con stock inicial
    //  POST /admin/inventario/ingredientes
    // ──────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombreIngrediente' => [
                'required',
                'string',
                'max:160',
                Rule::unique('ingrediente', 'nombreIngrediente')
            ],
            'unidad' => ['required', 'string', 'max:40'],
            'stock' => ['required', 'numeric', 'min:0'],
            'stock_minimo' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($data, &$ingrediente) {
            $ingrediente = Ingrediente::create([
                'nombreIngrediente' => $data['nombreIngrediente'],
                'unidad' => $data['unidad'],
            ]);

            InventarioIngrediente::create([
                'ingrediente_idIngrediente' => $ingrediente->idIngrediente,
                'stock' => $data['stock'],
                'stock_minimo' => $data['stock_minimo'],
                'actualizado_en' => now(),
            ]);

            // Registrar movimiento de entrada inicial si el stock > 0
            if ($data['stock'] > 0) {
                MovimientoIngrediente::create([
                    'tipo' => 'ENTRADA',
                    'cantidad' => $data['stock'],
                    'motivo' => 'Stock inicial al registrar ingrediente',
                    'referencia' => null,
                    'fecha' => now(),
                    'ingrediente_idIngrediente' => $ingrediente->idIngrediente,
                    'usuario_idUsuario' => Auth::id(),
                ]);
            }
        });

        $ingrediente->loadMissing('inventario');

        return response()->json([
            'message' => 'Ingrediente registrado.',
            'data' => $this->serialize($ingrediente),
        ], 201);
    }

    // ──────────────────────────────────────────────────────
    //  HU16 — Editar nombre, unidad y stock mínimo
    //  PUT /admin/inventario/ingredientes/{ingrediente}
    // ──────────────────────────────────────────────────────
    public function update(Request $request, Ingrediente $ingrediente): JsonResponse
    {
        $data = $request->validate([
            'nombreIngrediente' => [
                'required',
                'string',
                'max:160',
                Rule::unique('ingrediente', 'nombreIngrediente')
                    ->ignore($ingrediente->idIngrediente, 'idIngrediente')
            ],
            'unidad' => ['required', 'string', 'max:40'],
            'stock_minimo' => ['required', 'numeric', 'min:0'],
        ]);

        $ingrediente->update([
            'nombreIngrediente' => $data['nombreIngrediente'],
            'unidad' => $data['unidad'],
        ]);

        $ingrediente->inventario?->update([
            'stock_minimo' => $data['stock_minimo'],
            'actualizado_en' => now(),
        ]);

        $ingrediente->loadMissing('inventario');

        return response()->json([
            'message' => 'Ingrediente actualizado.',
            'data' => $this->serialize($ingrediente),
        ]);
    }

    // ──────────────────────────────────────────────────────
    //  HU16 — Ajustar stock (entrada, salida o ajuste directo)
    //  POST /admin/inventario/ingredientes/{ingrediente}/movimiento
    // ──────────────────────────────────────────────────────
    public function registrarMovimiento(Request $request, Ingrediente $ingrediente): JsonResponse
    {
        $data = $request->validate([
            'tipo' => ['required', Rule::in(['ENTRADA', 'SALIDA', 'AJUSTE'])],
            'cantidad' => ['required', 'numeric', 'min:0.0001'],
            'motivo' => ['required', 'string', 'max:120'],
            'referencia' => ['nullable', 'string', 'max:120'],
        ]);

        $inventario = $ingrediente->inventario;

        if (!$inventario) {
            return response()->json(['message' => 'Este ingrediente no tiene inventario registrado.'], 422);
        }

        DB::transaction(function () use ($data, $ingrediente, $inventario) {
            // Actualizar stock según tipo
            $stockAnterior = (float) $inventario->stock;

            if ($data['tipo'] === 'ENTRADA') {
                $nuevoStock = $stockAnterior + (float) $data['cantidad'];
            } elseif ($data['tipo'] === 'SALIDA') {
                $nuevoStock = max(0, $stockAnterior - (float) $data['cantidad']);
            } else {
                // AJUSTE → la cantidad es el nuevo valor absoluto de stock
                $nuevoStock = (float) $data['cantidad'];
            }

            $inventario->update([
                'stock' => $nuevoStock,
                'actualizado_en' => now(),
            ]);

            MovimientoIngrediente::create([
                'tipo' => $data['tipo'],
                'cantidad' => $data['cantidad'],
                'motivo' => $data['motivo'],
                'referencia' => $data['referencia'] ?? null,
                'fecha' => now(),
                'ingrediente_idIngrediente' => $ingrediente->idIngrediente,
                'usuario_idUsuario' => Auth::id(),
            ]);
        });

        $ingrediente->load('inventario');

        return response()->json([
            'message' => 'Movimiento registrado.',
            'data' => $this->serialize($ingrediente),
        ]);
    }

    // ──────────────────────────────────────────────────────
    //  HU17 — Panel de alertas: solo los que están bajo stock
    //  GET /admin/inventario/alertas
    // ──────────────────────────────────────────────────────
    public function alertas(): JsonResponse
    {
        $bajoStock = Ingrediente::query()
            ->with('inventario')
            ->whereHas(
                'inventario',
                fn($q) =>
                $q->whereColumn('stock', '<=', 'stock_minimo')
            )
            ->orderBy('nombreIngrediente')
            ->get()
            ->map(fn(Ingrediente $i) => $this->serialize($i));

        return response()->json([
            'total_alertas' => $bajoStock->count(),
            'data' => $bajoStock,
        ]);
    }

    // ──────────────────────────────────────────────────────
    //  Historial de movimientos de un ingrediente
    //  GET /admin/inventario/ingredientes/{ingrediente}/movimientos
    // ──────────────────────────────────────────────────────
    public function movimientos(Ingrediente $ingrediente): JsonResponse
    {
        $movimientos = $ingrediente->movimientos()
            ->with(['usuario:idUsuario,nombre,apellido,cargos_idCargo', 'usuario.cargo:idCargo,nombre'])
            ->orderByDesc('fecha')
            ->limit(50)
            ->get()
            ->map(fn (MovimientoIngrediente $m) => $this->serializeMovimiento($m));

        return response()->json([
            'ingrediente' => [
                'idIngrediente' => $ingrediente->idIngrediente,
                'nombreIngrediente' => $ingrediente->nombreIngrediente,
                'unidad' => $ingrediente->unidad,
            ],
            'data' => $movimientos,
        ]);
    }

    // ──────────────────────────────────────────────────────
    //  Historial global (admin) — incluye cambios del cocinero
    //  GET /admin/inventario/movimientos?solo_cocina=1
    // ──────────────────────────────────────────────────────
    public function historialGlobal(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'solo_cocina' => ['sometimes', 'boolean'],
            'ingrediente' => ['nullable', 'string', 'max:160'],
            'usuario' => ['nullable', 'string', 'max:160'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
        ]);

        $soloCocina = $request->boolean('solo_cocina');
        $nombreIngrediente = trim((string) ($filtros['ingrediente'] ?? ''));
        $nombreUsuario = trim((string) ($filtros['usuario'] ?? ''));

        $query = MovimientoIngrediente::query()
            ->with([
                'ingrediente:idIngrediente,nombreIngrediente,unidad',
                'usuario:idUsuario,nombre,apellido,cargos_idCargo',
                'usuario.cargo:idCargo,nombre',
            ])
            ->orderByDesc('fecha')
            ->limit(250);

        if ($soloCocina) {
            $query->whereHas('usuario.cargo', fn ($q) => $q->where('nombre', 'COCINERO'));
        }

        if ($nombreIngrediente !== '') {
            $term = '%'.$nombreIngrediente.'%';
            $query->whereHas('ingrediente', fn ($q) => $q->where('nombreIngrediente', 'like', $term));
        }

        if ($nombreUsuario !== '') {
            $term = '%'.$nombreUsuario.'%';
            $query->whereHas('usuario', function ($q) use ($term) {
                $q->where(function ($sub) use ($term) {
                    $sub->where('nombre', 'like', $term)
                        ->orWhere('apellido', 'like', $term)
                        ->orWhereRaw("CONCAT(nombre, ' ', apellido) LIKE ?", [$term]);
                });
            });
        }

        if (! empty($filtros['fecha_desde'])) {
            $query->whereDate('fecha', '>=', $filtros['fecha_desde']);
        }

        if (! empty($filtros['fecha_hasta'])) {
            $query->whereDate('fecha', '<=', $filtros['fecha_hasta']);
        }

        $movimientos = $query->get()->map(fn (MovimientoIngrediente $m) => $this->serializeMovimiento($m, true));

        return response()->json([
            'total' => $movimientos->count(),
            'data' => $movimientos,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMovimiento(MovimientoIngrediente $m, bool $incluirIngrediente = false): array
    {
        $payload = [
            'idMovimiento' => $m->idMovimiento,
            'tipo' => $m->tipo,
            'cantidad' => (float) $m->cantidad,
            'motivo' => $m->motivo,
            'referencia' => $m->referencia,
            'fecha' => $m->fecha?->toISOString(),
            'usuario' => $m->usuario
                ? trim($m->usuario->nombre.' '.$m->usuario->apellido)
                : '—',
            'usuario_rol' => $m->usuario?->cargo?->nombre ?? null,
        ];

        if ($incluirIngrediente && $m->ingrediente) {
            $payload['ingrediente'] = [
                'idIngrediente' => $m->ingrediente->idIngrediente,
                'nombreIngrediente' => $m->ingrediente->nombreIngrediente,
                'unidad' => $m->ingrediente->unidad,
            ];
        }

        return $payload;
    }

    // ──────────────────────────────────────────────────────
    //  Serializer privado
    // ──────────────────────────────────────────────────────
    private function serialize(Ingrediente $i): array
    {
        $inv = $i->inventario;
        $stock = $inv ? (float) $inv->stock : 0.0;
        $stockMin = $inv ? (float) $inv->stock_minimo : 0.0;
        $alertaStock = $stock <= $stockMin;

        return [
            'idIngrediente' => $i->idIngrediente,
            'nombreIngrediente' => $i->nombreIngrediente,
            'unidad' => $i->unidad,
            'stock' => $stock,
            'stock_minimo' => $stockMin,
            'actualizado_en' => $inv?->actualizado_en?->toISOString(),
            'alerta_stock' => $alertaStock,
            'porcentaje_stock' => $stockMin > 0
                ? round(min(($stock / $stockMin) * 100, 999))
                : null,
        ];
    }
}