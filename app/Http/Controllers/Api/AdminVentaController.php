<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Venta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminVentaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'estado' => ['nullable', 'string', 'in:ACTIVA,CANCELADA,todas'],
            'cajero' => ['nullable', 'string', 'max:120'],
            'metodo' => ['nullable', 'string', 'in:EFECTIVO,TARJETA,NEQUI,DAVIPLATA'],
            'producto' => ['nullable', 'string', 'max:120'],
        ]);

        $desde = $data['desde'] ?? now()->subDays(30)->toDateString();
        $hasta = $data['hasta'] ?? now()->toDateString();
        $estado = $data['estado'] ?? 'todas';

        $query = Venta::query()
            ->with([
                'pagos',
                'cajero:idUsuario,nombre,apellido',
                'canceladaPor:idUsuario,nombre,apellido',
                'pedido.mesa:idMesa,numero,nombre',
                'pedido.mesero:idUsuario,nombre,apellido',
                'pedido.detalles' => fn ($q) => $q
                    ->where('estado_item', '!=', 'CANCELADO')
                    ->orderBy('idPedidoDetalle')
                    ->with('producto:idProducto,nombreProducto'),
            ])
            ->whereDate('registrada_en', '>=', $desde)
            ->whereDate('registrada_en', '<=', $hasta)
            ->orderByDesc('registrada_en');

        if ($estado === 'ACTIVA') {
            $query->activas();
        } elseif ($estado === 'CANCELADA') {
            $query->where('estado', 'CANCELADA');
        }

        if (! empty($data['cajero'])) {
            $cajero = $data['cajero'];
            $query->whereHas('cajero', function ($q) use ($cajero) {
                $q->where('nombre', 'like', "%{$cajero}%")
                    ->orWhere('apellido', 'like', "%{$cajero}%");
            });
        }

        if (! empty($data['metodo'])) {
            $metodo = $data['metodo'];
            $query->whereHas('pagos', function ($q) use ($metodo) {
                $q->where('metodo', $metodo);
            });
        }

        if (! empty($data['producto'])) {
            $producto = $data['producto'];
            $query->whereHas('pedido.detalles.producto', function ($q) use ($producto) {
                $q->where('nombreProducto', 'like', "%{$producto}%");
            });
        }

        $ventas = $query->limit(500)->get();
        $activas = $ventas->filter(fn (Venta $v) => $v->estado !== 'CANCELADA');

        return response()->json([
            'desde' => $desde,
            'hasta' => $hasta,
            'total_periodo' => round($activas->sum('total'), 2),
            'num_ventas' => $ventas->count(),
            'num_canceladas' => $ventas->where('estado', 'CANCELADA')->count(),
            'data' => $ventas->map(fn (Venta $v) => $this->serializeVenta($v)),
        ]);
    }

    public function notificacionesPendientes(): JsonResponse
    {
        $pendientes = Venta::query()
            ->where('estado', 'CANCELADA')
            ->where('admin_visto', false)
            ->count();

        return response()->json([
            'cancelaciones_sin_ver' => $pendientes,
        ]);
    }

    public function marcarNotificacionesVistas(): JsonResponse
    {
        $actualizadas = Venta::query()
            ->where('estado', 'CANCELADA')
            ->where('admin_visto', false)
            ->update(['admin_visto' => true]);

        return response()->json([
            'message' => 'Notificaciones marcadas como vistas.',
            'actualizadas' => $actualizadas,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeVenta(Venta $venta): array
    {
        $pedido = $venta->pedido;
        $cajero = $venta->cajero;
        $canceladaPor = $venta->canceladaPor;

        return [
            'idVenta' => $venta->idVenta,
            'estado' => $venta->estado ?? 'ACTIVA',
            'subtotal' => $venta->subtotal,
            'impuesto_o_servicio' => $venta->impuesto_o_servicio,
            'total' => $venta->total,
            'registrada_en' => $venta->registrada_en?->toIso8601String(),
            'motivo_cancelacion' => $venta->motivo_cancelacion,
            'cancelada_en' => $venta->cancelada_en?->toIso8601String(),
            'admin_visto' => (bool) $venta->admin_visto,
            'cajero' => $cajero ? [
                'idUsuario' => $cajero->idUsuario,
                'nombre' => $cajero->nombre,
                'apellido' => $cajero->apellido,
            ] : null,
            'cancelada_por' => $canceladaPor ? [
                'idUsuario' => $canceladaPor->idUsuario,
                'nombre' => $canceladaPor->nombre,
                'apellido' => $canceladaPor->apellido,
            ] : null,
            'pagos' => $venta->pagos->map(fn ($p) => [
                'idPago' => $p->idPago,
                'metodo' => $p->metodo,
                'valor' => $p->valor,
                'referencia' => $p->referencia,
                'pagado_en' => $p->pagado_en?->toIso8601String(),
            ]),
            'pedido' => $pedido ? [
                'idPedido' => $pedido->idPedido,
                'estado' => $pedido->estado,
                'mesa' => $pedido->mesa ? [
                    'idMesa' => $pedido->mesa->idMesa,
                    'numero' => $pedido->mesa->numero,
                    'nombre' => $pedido->mesa->nombre,
                ] : null,
                'mesero' => $pedido->mesero ? [
                    'nombre' => $pedido->mesero->nombre,
                    'apellido' => $pedido->mesero->apellido,
                ] : null,
                'detalles' => $pedido->relationLoaded('detalles')
                    ? $pedido->detalles->map(fn ($d) => [
                        'idPedidoDetalle' => $d->idPedidoDetalle,
                        'cantidad' => $d->cantidad,
                        'precio_unitario' => $d->precio_unitario,
                        'producto' => $d->producto ? [
                            'nombreProducto' => $d->producto->nombreProducto,
                        ] : null,
                    ])
                    : [],
            ] : null,
        ];
    }
}
