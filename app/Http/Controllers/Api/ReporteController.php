<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReporteController extends Controller
{
    /**
     * HU13 — Historial de ventas del día con filtros opcionales.
     * GET /admin/reportes/ventas-hoy
     * Query params: hora_desde, hora_hasta, metodo (EFECTIVO|TARJETA|NEQUI|DAVIPLATA)
     */
    public function ventasHoy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hora_desde' => ['nullable', 'date_format:H:i'],
            'hora_hasta' => ['nullable', 'date_format:H:i'],
            'metodo' => ['nullable', 'string', 'in:EFECTIVO,TARJETA,NEQUI,DAVIPLATA'],
        ]);

        $hoy = now()->toDateString();

        $query = DB::table('venta as v')
            ->join('pedido as p', 'p.idPedido', '=', 'v.pedido_idPedido')
            ->join('mesa as m', 'm.idMesa', '=', 'p.mesa_idMesa')
            ->leftJoin('pago as pg', 'pg.venta_idVenta', '=', 'v.idVenta')
            ->where(function ($q) {
                $q->where('v.estado', 'ACTIVA')->orWhereNull('v.estado');
            })
            ->whereDate('v.registrada_en', $hoy)
            ->select(
                'v.idVenta',
                'v.subtotal',
                'v.impuesto_o_servicio',
                'v.total',
                'v.registrada_en',
                'm.numero as mesa_numero',
                'm.nombre as mesa_nombre',
                DB::raw("GROUP_CONCAT(DISTINCT pg.metodo ORDER BY pg.metodo SEPARATOR ', ') as metodos_pago")
            )
            ->groupBy(
                'v.idVenta',
                'v.subtotal',
                'v.impuesto_o_servicio',
                'v.total',
                'v.registrada_en',
                'm.numero',
                'm.nombre'
            )
            ->orderByDesc('v.registrada_en');

        if (!empty($validated['hora_desde'])) {
            $query->whereTime('v.registrada_en', '>=', $validated['hora_desde']);
        }
        if (!empty($validated['hora_hasta'])) {
            $query->whereTime('v.registrada_en', '<=', $validated['hora_hasta']);
        }
        if (!empty($validated['metodo'])) {
            $query->whereExists(function ($sub) use ($validated) {
                $sub->select(DB::raw(1))
                    ->from('pago')
                    ->whereColumn('pago.venta_idVenta', 'v.idVenta')
                    ->where('pago.metodo', $validated['metodo']);
            });
        }

        $ventas = $query->get();
        $totalDia = $ventas->sum('total');

        return response()->json([
            'fecha' => $hoy,
            'total_dia' => round($totalDia, 2),
            'num_ventas' => $ventas->count(),
            'data' => $ventas,
        ]);
    }

    /**
     * HU14 — Reporte de ventas por rango de fechas.
     * GET /admin/reportes/ventas
     * Query params: fecha_desde (required), fecha_hasta (required)
     */
    public function ventasPorFecha(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fecha_desde' => ['required', 'date'],
            'fecha_hasta' => ['required', 'date', 'after_or_equal:fecha_desde'],
        ]);

        // Detalle de ventas en el rango
        $ventas = DB::table('venta as v')
            ->join('pedido as p', 'p.idPedido', '=', 'v.pedido_idPedido')
            ->join('mesa as m', 'm.idMesa', '=', 'p.mesa_idMesa')
            ->leftJoin('pago as pg', 'pg.venta_idVenta', '=', 'v.idVenta')
            ->where(function ($q) {
                $q->where('v.estado', 'ACTIVA')->orWhereNull('v.estado');
            })
            ->whereBetween(DB::raw('DATE(v.registrada_en)'), [
                $validated['fecha_desde'],
                $validated['fecha_hasta'],
            ])
            ->select(
                'v.idVenta',
                'v.subtotal',
                'v.impuesto_o_servicio',
                'v.total',
                'v.registrada_en',
                'm.numero as mesa_numero',
                'm.nombre as mesa_nombre',
                DB::raw("GROUP_CONCAT(DISTINCT pg.metodo ORDER BY pg.metodo SEPARATOR ', ') as metodos_pago")
            )
            ->groupBy(
                'v.idVenta',
                'v.subtotal',
                'v.impuesto_o_servicio',
                'v.total',
                'v.registrada_en',
                'm.numero',
                'm.nombre'
            )
            ->orderByDesc('v.registrada_en')
            ->get();

        // Agrupación por día para gráfica
        $porDia = DB::table('venta')
            ->where(function ($q) {
                $q->where('estado', 'ACTIVA')->orWhereNull('estado');
            })
            ->whereBetween(DB::raw('DATE(registrada_en)'), [
                $validated['fecha_desde'],
                $validated['fecha_hasta'],
            ])
            ->select(
                DB::raw('DATE(registrada_en) as fecha'),
                DB::raw('COUNT(*) as num_ventas'),
                DB::raw('SUM(total) as total_dia')
            )
            ->groupBy(DB::raw('DATE(registrada_en)'))
            ->orderBy('fecha')
            ->get();

        $totalVentas = $ventas->sum('total');
        $numPedidos = $ventas->count();
        $promedioPedido = $numPedidos > 0 ? round($totalVentas / $numPedidos, 2) : 0;

        return response()->json([
            'fecha_desde' => $validated['fecha_desde'],
            'fecha_hasta' => $validated['fecha_hasta'],
            'total_ventas' => round($totalVentas, 2),
            'num_pedidos' => $numPedidos,
            'promedio_pedido' => $promedioPedido,
            'por_dia' => $porDia,
            'data' => $ventas,
        ]);
    }

    /**
     * HU15 — Ranking de productos más vendidos.
     * GET /admin/reportes/productos-mas-vendidos
     * Query params: fecha_desde (optional), fecha_hasta (optional)
     */
    public function productosMasVendidos(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
        ]);

        $query = DB::table('pedido_detalle as pd')
            ->join('producto as pr', 'pr.idProducto', '=', 'pd.producto_idProducto')
            ->join('pedido as pe', 'pe.idPedido', '=', 'pd.pedido_idPedido')
            ->join('venta as v', 'v.pedido_idPedido', '=', 'pe.idPedido') // solo pedidos cobrados
            ->select(
                'pr.idProducto',
                'pr.nombreProducto',
                'pr.precio',
                DB::raw('SUM(pd.cantidad) as total_vendido'),
                DB::raw('SUM(pd.cantidad * pd.precio_unitario) as ingreso_total')
            )
            ->groupBy('pr.idProducto', 'pr.nombreProducto', 'pr.precio')
            ->orderByDesc('total_vendido')
            ->limit(20);

        if (!empty($validated['fecha_desde'])) {
            $query->whereDate('v.registrada_en', '>=', $validated['fecha_desde']);
        }
        if (!empty($validated['fecha_hasta'])) {
            $query->whereDate('v.registrada_en', '<=', $validated['fecha_hasta']);
        }

        $ranking = $query->get();

        return response()->json([
            'fecha_desde' => $validated['fecha_desde'] ?? null,
            'fecha_hasta' => $validated['fecha_hasta'] ?? null,
            'data' => $ranking,
        ]);
    }
}