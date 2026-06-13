<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gasto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GastoController extends Controller
{
    // Categorías válidas según el comentario del SQL
    const CATEGORIAS = ['arriendo', 'servicios', 'insumos', 'otros'];

    // ──────────────────────────────────────────────────────
    //  HU18 — Listar gastos con filtros opcionales
    //  GET /admin/finanzas/gastos
    //  Query: fecha_desde, fecha_hasta, categoria
    // ──────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'categoria' => ['nullable', 'string', Rule::in(self::CATEGORIAS)],
        ]);

        $query = Gasto::query()
            ->with('registradoPor:idUsuario,nombre,apellido')
            ->orderByDesc('fecha');

        if (!empty($validated['fecha_desde'])) {
            $query->whereDate('fecha', '>=', $validated['fecha_desde']);
        }
        if (!empty($validated['fecha_hasta'])) {
            $query->whereDate('fecha', '<=', $validated['fecha_hasta']);
        }
        if (!empty($validated['categoria'])) {
            $query->where('categoria', $validated['categoria']);
        }

        $gastos = $query->get()->map(fn(Gasto $g) => $this->serialize($g));

        return response()->json([
            'total_gastos' => $gastos->count(),
            'suma_total' => round($gastos->sum('valor'), 2),
            'data' => $gastos,
        ]);
    }

    // ──────────────────────────────────────────────────────
    //  HU18 — Registrar un gasto
    //  POST /admin/finanzas/gastos
    // ──────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'categoria' => ['required', 'string', Rule::in(self::CATEGORIAS)],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'fecha' => ['required', 'date'],
            'metodo' => ['nullable', 'string', Rule::in(['EFECTIVO', 'TARJETA', 'NEQUI', 'DAVIPLATA', 'OTRO'])],
        ]);

        $gasto = Gasto::create([
            'categoria' => $data['categoria'],
            'descripcion' => $data['descripcion'] ?? null,
            'valor' => $data['valor'],
            'fecha' => $data['fecha'],
            'metodo' => $data['metodo'] ?? null,
            'registrado_por_idUsuario' => Auth::id(),
        ]);

        $gasto->loadMissing('registradoPor:idUsuario,nombre,apellido');

        return response()->json([
            'message' => 'Gasto registrado.',
            'data' => $this->serialize($gasto),
        ], 201);
    }

    // ──────────────────────────────────────────────────────
    //  HU18 — Editar un gasto
    //  PUT /admin/finanzas/gastos/{gasto}
    // ──────────────────────────────────────────────────────
    public function update(Request $request, Gasto $gasto): JsonResponse
    {
        $data = $request->validate([
            'categoria' => ['required', 'string', Rule::in(self::CATEGORIAS)],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'fecha' => ['required', 'date'],
            'metodo' => ['nullable', 'string', Rule::in(['EFECTIVO', 'TARJETA', 'NEQUI', 'DAVIPLATA', 'OTRO'])],
        ]);

        $gasto->update([
            'categoria' => $data['categoria'],
            'descripcion' => $data['descripcion'] ?? null,
            'valor' => $data['valor'],
            'fecha' => $data['fecha'],
            'metodo' => $data['metodo'] ?? null,
        ]);

        $gasto->loadMissing('registradoPor:idUsuario,nombre,apellido');

        return response()->json([
            'message' => 'Gasto actualizado.',
            'data' => $this->serialize($gasto),
        ]);
    }

    // ──────────────────────────────────────────────────────
    //  HU18 — Eliminar un gasto
    //  DELETE /admin/finanzas/gastos/{gasto}
    // ──────────────────────────────────────────────────────
    public function destroy(Gasto $gasto): JsonResponse
    {
        $gasto->delete();

        return response()->json(['message' => 'Gasto eliminado.']);
    }

    // ──────────────────────────────────────────────────────
    //  HU19 — Ganancias y pérdidas por período
    //  GET /admin/finanzas/pyg
    //  Query: fecha_desde (required), fecha_hasta (required)
    // ──────────────────────────────────────────────────────
    public function pyg(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fecha_desde' => ['required', 'date'],
            'fecha_hasta' => ['required', 'date', 'after_or_equal:fecha_desde'],
        ]);

        $desde = $validated['fecha_desde'];
        $hasta = $validated['fecha_hasta'];

        // ── Ingresos: ventas cerradas en el período ────────
        $totalIngresos = DB::table('venta')
            ->whereBetween(DB::raw('DATE(registrada_en)'), [$desde, $hasta])
            ->sum('total');

        // Ingresos agrupados por día (para gráfica)
        $ingresosPorDia = DB::table('venta')
            ->whereBetween(DB::raw('DATE(registrada_en)'), [$desde, $hasta])
            ->select(
                DB::raw('DATE(registrada_en) as fecha'),
                DB::raw('SUM(total) as total_ingresos'),
                DB::raw('COUNT(*) as num_ventas')
            )
            ->groupBy(DB::raw('DATE(registrada_en)'))
            ->orderBy('fecha')
            ->get();

        // ── Gastos del período ─────────────────────────────
        $totalGastos = DB::table('gasto')
            ->whereBetween(DB::raw('DATE(fecha)'), [$desde, $hasta])
            ->sum('valor');

        // Gastos agrupados por día (para gráfica)
        $gastosPorDia = DB::table('gasto')
            ->whereBetween(DB::raw('DATE(fecha)'), [$desde, $hasta])
            ->select(
                DB::raw('DATE(fecha) as fecha'),
                DB::raw('SUM(valor) as total_gastos'),
                DB::raw('COUNT(*) as num_gastos')
            )
            ->groupBy(DB::raw('DATE(fecha)'))
            ->orderBy('fecha')
            ->get();

        // Gastos agrupados por categoría
        $gastosPorCategoria = DB::table('gasto')
            ->whereBetween(DB::raw('DATE(fecha)'), [$desde, $hasta])
            ->select(
                'categoria',
                DB::raw('SUM(valor) as total'),
                DB::raw('COUNT(*) as cantidad')
            )
            ->groupBy('categoria')
            ->orderByDesc('total')
            ->get();

        // ── Utilidad neta ──────────────────────────────────
        $utilidad = round((float) $totalIngresos - (float) $totalGastos, 2);
        $totalIngresos = round((float) $totalIngresos, 2);
        $totalGastos = round((float) $totalGastos, 2);
        $margenPct = $totalIngresos > 0
            ? round(($utilidad / $totalIngresos) * 100, 1)
            : null;

        return response()->json([
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'total_ingresos' => $totalIngresos,
            'total_gastos' => $totalGastos,
            'utilidad_neta' => $utilidad,
            'margen_pct' => $margenPct,
            'ingresos_por_dia' => $ingresosPorDia,
            'gastos_por_dia' => $gastosPorDia,
            'gastos_por_categoria' => $gastosPorCategoria,
        ]);
    }

    // ──────────────────────────────────────────────────────
    //  Serializer privado
    // ──────────────────────────────────────────────────────
    private function serialize(Gasto $g): array
    {
        return [
            'idGasto' => $g->idGasto,
            'categoria' => $g->categoria,
            'descripcion' => $g->descripcion,
            'valor' => (float) $g->valor,
            'fecha' => $g->fecha?->toISOString(),
            'metodo' => $g->metodo,
            'registrado_por' => $g->registradoPor
                ? $g->registradoPor->nombre . ' ' . $g->registradoPor->apellido
                : '—',
        ];
    }
}