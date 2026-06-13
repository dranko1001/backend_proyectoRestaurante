<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gasto;
use App\Models\Mesa;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\Reserva;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Carbon::setLocale('es');

        $tz = config('app.timezone', 'UTC');
        $now = Carbon::now($tz);

        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();

        $weekStart = $now->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $weekEnd = $now->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        $ingresosMes = (float) Venta::query()
            ->activas()
            ->whereBetween('registrada_en', [$monthStart, $monthEnd])
            ->sum('total');

        $numVentasMes = (int) Venta::query()
            ->activas()
            ->whereBetween('registrada_en', [$monthStart, $monthEnd])
            ->count();

        $ticketPromedioMes = $numVentasMes > 0 ? round($ingresosMes / $numVentasMes, 2) : 0.0;

        $pedidosSemana = (int) Pedido::query()
            ->whereBetween('creado_en', [$weekStart, $weekEnd])
            ->count();

        $pedidosActivos = (int) Pedido::query()
            ->whereIn('estado', ['PENDIENTE', 'EN_PREPARACION', 'LISTO'])
            ->count();

        $pedidosEnCocina = (int) Pedido::query()
            ->whereIn('estado', ['PENDIENTE', 'EN_PREPARACION'])
            ->count();

        $mesasActivas = (int) Mesa::query()->where('activa', true)->count();
        $mesasOcupadas = (int) Mesa::query()->where('activa', true)->where('estado', 'OCUPADA')->count();

        $gastosMes = (float) Gasto::query()
            ->whereBetween('fecha', [$monthStart, $monthEnd])
            ->sum('valor');

        $utilidadNetaMes = round($ingresosMes - $gastosMes, 2);

        $topProductosRows = PedidoDetalle::query()
            ->select([
                'pedido_detalle.producto_idProducto',
                'producto.nombreProducto',
                DB::raw('SUM(pedido_detalle.cantidad) as unidades_vendidas'),
                DB::raw('SUM(pedido_detalle.cantidad * pedido_detalle.precio_unitario) as ingreso_producto'),
            ])
            ->join('pedido', 'pedido.idPedido', '=', 'pedido_detalle.pedido_idPedido')
            ->join('venta', 'venta.pedido_idPedido', '=', 'pedido.idPedido')
            ->join('producto', 'producto.idProducto', '=', 'pedido_detalle.producto_idProducto')
            ->whereBetween('venta.registrada_en', [$monthStart, $monthEnd])
            ->groupBy('pedido_detalle.producto_idProducto', 'producto.nombreProducto')
            ->orderByDesc('unidades_vendidas')
            ->limit(8)
            ->get();

        $platilloMasVendidoMes = $topProductosRows->first();

        $pagosPorMetodo = Pago::query()
            ->select(['pago.metodo', DB::raw('SUM(pago.valor) as total')])
            ->join('venta', 'venta.idVenta', '=', 'pago.venta_idVenta')
            ->whereBetween('venta.registrada_en', [$monthStart, $monthEnd])
            ->groupBy('pago.metodo')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'metodo' => $row->metodo,
                'etiqueta' => $this->etiquetaMetodoPago((string) $row->metodo),
                'total' => (float) $row->total,
            ]);

        $serieUltimos7Dias = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = $now->copy()->subDays($i)->startOfDay();
            $dayEnd = $day->copy()->endOfDay();
            $serieUltimos7Dias[] = [
                'fecha' => $day->toDateString(),
                'etiqueta' => $day->translatedFormat('D j'),
                'pedidos' => (int) Pedido::query()
                    ->whereBetween('creado_en', [$day, $dayEnd])
                    ->count(),
                'ingresos' => (float) Venta::query()
                    ->whereBetween('registrada_en', [$day, $dayEnd])
                    ->sum('total'),
            ];
        }

        $pedidosPorEstado = Pedido::query()
            ->select(['estado', DB::raw('COUNT(*) as total')])
            ->groupBy('estado')
            ->get()
            ->map(fn ($row) => [
                'estado' => $row->estado,
                'total' => (int) $row->total,
            ]);

        $desdeNuevas = $now->copy()->subHours(24);

        $reservasProximas = Reserva::query()
            ->with([
                'mesa:idMesa,numero,nombre',
                'cliente:idUsuario,nombre,apellido,correo',
            ])
            ->whereIn('estado', ['CONFIRMADA', 'SOLICITADA'])
            ->where('fecha_hora', '>=', $now)
            ->orderBy('fecha_hora')
            ->limit(30)
            ->get();

        $reservasNuevas24h = (int) Reserva::query()
            ->whereIn('estado', ['CONFIRMADA', 'SOLICITADA'])
            ->where('creado_en', '>=', $desdeNuevas)
            ->count();

        return response()->json([
            'data' => [
                'generado_en' => $now->toIso8601String(),
                'periodo' => [
                    'mes_etiqueta' => $now->translatedFormat('F Y'),
                    'mes_desde' => $monthStart->toIso8601String(),
                    'mes_hasta' => $monthEnd->toIso8601String(),
                    'semana_desde' => $weekStart->toIso8601String(),
                    'semana_hasta' => $weekEnd->toIso8601String(),
                ],
                'resumen' => [
                    'ingresos_ventas_mes_cop' => $ingresosMes,
                    'gastos_mes_cop' => $gastosMes,
                    'utilidad_neta_mes_cop' => $utilidadNetaMes,
                    'num_ventas_mes' => $numVentasMes,
                    'ticket_promedio_mes_cop' => $ticketPromedioMes,
                    'pedidos_creados_semana' => $pedidosSemana,
                    'pedidos_activos_salon' => $pedidosActivos,
                    'pedidos_en_cocina' => $pedidosEnCocina,
                    'mesas_ocupadas' => $mesasOcupadas,
                    'mesas_activas' => $mesasActivas,
                ],
                'platillo_mas_vendido_mes' => $platilloMasVendidoMes ? [
                    'idProducto' => (int) $platilloMasVendidoMes->producto_idProducto,
                    'nombre' => (string) $platilloMasVendidoMes->nombreProducto,
                    'unidades_vendidas' => (int) $platilloMasVendidoMes->unidades_vendidas,
                    'ingreso_cop' => (float) $platilloMasVendidoMes->ingreso_producto,
                ] : null,
                'top_productos_mes' => $topProductosRows->map(fn ($row) => [
                    'idProducto' => (int) $row->producto_idProducto,
                    'nombre' => (string) $row->nombreProducto,
                    'unidades_vendidas' => (int) $row->unidades_vendidas,
                    'ingreso_cop' => (float) $row->ingreso_producto,
                ])->values()->all(),
                'pagos_por_metodo_mes' => $pagosPorMetodo,
                'serie_ultimos_7_dias' => $serieUltimos7Dias,
                'pedidos_por_estado' => $pedidosPorEstado,
                'reservas' => [
                    'nuevas_ultimas_24h' => $reservasNuevas24h,
                    'proximas' => $reservasProximas->map(function (Reserva $r) use ($desdeNuevas) {
                        /** @var \Carbon\Carbon|null $fh */
                        $fh = $r->fecha_hora;
                        /** @var \Carbon\Carbon|null $creado */
                        $creado = $r->creado_en;
                        $cliente = $r->cliente;
                        $nombreCliente = $cliente
                            ? trim(($cliente->nombre ?? '').' '.($cliente->apellido ?? '')) ?: $cliente->correo
                            : 'Cliente';

                        return [
                            'idReserva' => $r->idReserva,
                            'fecha_hora' => $fh ? $fh->timezone(config('app.timezone'))->format(\DateTime::ATOM) : null,
                            'num_personas' => $r->num_personas,
                            'estado' => $r->estado,
                            'notas' => $r->notas,
                            'creado_en' => $creado ? $creado->timezone(config('app.timezone'))->format(\DateTime::ATOM) : null,
                            'es_nueva' => $creado && $creado->gte($desdeNuevas),
                            'cliente_nombre' => $nombreCliente,
                            'cliente_email' => $cliente?->correo,
                            'mesa' => $r->mesa ? [
                                'numero' => $r->mesa->numero,
                                'nombre' => $r->mesa->nombre,
                            ] : null,
                        ];
                    })->values()->all(),
                ],
            ],
        ]);
    }

    private function etiquetaMetodoPago(string $metodo): string
    {
        return match (strtoupper($metodo)) {
            'EFECTIVO' => 'Efectivo',
            'TARJETA' => 'Tarjeta',
            'NEQUI' => 'Nequi',
            'DAVIPLATA' => 'Daviplata',
            default => ucfirst(strtolower($metodo)),
        };
    }
}
