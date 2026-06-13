<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CocinaLlamadaMesero;
use App\Models\Mesa;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Reserva;
use App\Models\RestauranteConfig;
use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CajeroController extends Controller
{
    /**
     * Cuentas listas para cobrar (sin venta registrada).
     */
    public function cuentasPendientes(): JsonResponse
    {
        $pedidos = Pedido::query()
            ->whereNotNull('enviado_caja_en')
            ->whereNotIn('estado', ['CERRADO', 'CANCELADO'])
            ->whereDoesntHave('venta')
            ->with([
                'mesa:idMesa,numero,nombre',
                'mesero:idUsuario,nombre,apellido',
                'detalles' => fn ($q) => $q
                    ->where('estado_item', '!=', 'CANCELADO')
                    ->orderBy('idPedidoDetalle')
                    ->with('producto:idProducto,nombreProducto'),
            ])
            ->orderBy('enviado_caja_en')
            ->get();

        return response()->json([
            'data' => $pedidos->map(fn (Pedido $p) => $this->serializeCuentaResumen($p)),
        ]);
    }

    public function showPedido(Pedido $pedido): JsonResponse
    {
        if ($pedido->enviado_caja_en === null) {
            return response()->json([
                'message' => 'El mesero aún no ha enviado esta cuenta a caja.',
            ], 422);
        }

        if (in_array($pedido->estado, ['CERRADO', 'CANCELADO'], true)) {
            return response()->json([
                'message' => 'Este pedido ya está cerrado o cancelado.',
            ], 422);
        }

        if ($pedido->venta()->exists()) {
            return response()->json([
                'message' => 'Esta cuenta ya fue cobrada.',
            ], 422);
        }

        $pedido->load([
            'mesa:idMesa,numero,nombre',
            'mesero:idUsuario,nombre,apellido',
            'detalles' => fn ($q) => $q
                ->where('estado_item', '!=', 'CANCELADO')
                ->orderBy('idPedidoDetalle')
                ->with('producto:idProducto,nombreProducto,tipo'),
        ]);

        return response()->json([
            'data' => $this->serializeCuentaDetalle($pedido),
        ]);
    }

    public function cobrar(Request $request, Pedido $pedido): JsonResponse
    {
        /** @var Usuario $cajero */
        $cajero = $request->user();

        if ($pedido->enviado_caja_en === null) {
            return response()->json([
                'message' => 'El mesero aún no ha enviado esta cuenta a caja.',
            ], 422);
        }

        if (in_array($pedido->estado, ['CERRADO', 'CANCELADO'], true)) {
            return response()->json([
                'message' => 'Este pedido ya está cerrado o cancelado.',
            ], 422);
        }

        if ($pedido->venta()->exists()) {
            return response()->json([
                'message' => 'Esta cuenta ya fue cobrada.',
            ], 422);
        }

        $pedido->load([
            'mesa:idMesa,numero,nombre',
            'mesero:idUsuario,nombre,apellido',
            'detalles' => fn ($q) => $q->where('estado_item', '!=', 'CANCELADO'),
        ]);

        if (! $this->pedidoListoParaCobro($pedido)) {
            return response()->json([
                'message' => 'Aún hay platos en cocina. Espera a que estén listos.',
            ], 422);
        }

        $data = $request->validate([
            'impuesto_o_servicio' => ['nullable', 'numeric', 'min:0'],
            'pagos' => ['required', 'array', 'min:1'],
            'pagos.*.metodo' => ['required', 'string', 'in:EFECTIVO,TARJETA,NEQUI,DAVIPLATA'],
            'pagos.*.valor' => ['required', 'numeric', 'min:0.01'],
            'pagos.*.referencia' => ['nullable', 'string', 'max:120'],
        ]);

        $subtotal = round($this->calcularSubtotal($pedido), 2);
        $impuesto = round((float) ($data['impuesto_o_servicio'] ?? 0), 2);
        $total = round($subtotal + $impuesto, 2);
        $sumPagos = round(collect($data['pagos'])->sum(fn ($p) => (float) $p['valor']), 2);

        // Se permite recibir de más (el cliente paga con un billete grande): el
        // excedente queda como devolución/vuelto. Lo que no se permite es pagar de menos.
        if ($sumPagos + 0.01 < $total) {
            return response()->json([
                'message' => 'El total recibido no puede ser menor al total a cobrar.',
                'total_esperado' => $total,
                'total_pagos' => $sumPagos,
            ], 422);
        }

        $recibido = $sumPagos;
        $cambio = round($recibido - $total, 2);

        $ahora = now();

        $venta = DB::transaction(function () use ($pedido, $cajero, $data, $subtotal, $impuesto, $total, $recibido, $cambio, $ahora) {
            $venta = Venta::create([
                'pedido_idPedido' => $pedido->idPedido,
                'subtotal' => $subtotal,
                'impuesto_o_servicio' => $impuesto,
                'total' => $total,
                'recibido' => $recibido,
                'cambio' => $cambio,
                'registrada_en' => $ahora,
                'cajero_idUsuario' => $cajero->idUsuario,
                'estado' => 'ACTIVA',
                'admin_visto' => true,
            ]);

            // El consecutivo de factura se deriva del id (único y secuencial).
            $venta->numero_factura = 'FAC-'.str_pad((string) $venta->idVenta, 6, '0', STR_PAD_LEFT);
            $venta->save();

            foreach ($data['pagos'] as $pagoData) {
                Pago::create([
                    'venta_idVenta' => $venta->idVenta,
                    'metodo' => $pagoData['metodo'],
                    'valor' => round((float) $pagoData['valor'], 2),
                    'referencia' => $pagoData['referencia'] ?? null,
                    'pagado_en' => $ahora,
                ]);
            }

            $pedido->estado = 'CERRADO';
            $pedido->cerrado_en = $ahora;
            $pedido->actualizado_en = $ahora;
            $pedido->save();

            $mesa = Mesa::query()->where('idMesa', $pedido->mesa_idMesa)->first();
            if ($mesa) {
                $mesa->estado = 'LIBRE';
                $mesa->save();
            }

            return $venta;
        });

        $venta->load([
            'pagos',
            'pedido.mesa:idMesa,numero,nombre',
            'pedido.mesero:idUsuario,nombre,apellido',
            'pedido.detalles' => fn ($q) => $q
                ->where('estado_item', '!=', 'CANCELADO')
                ->orderBy('idPedidoDetalle')
                ->with('producto:idProducto,nombreProducto,tipo'),
        ]);

        return response()->json([
            'message' => 'Cuenta cobrada. La mesa quedó libre.',
            'data' => $this->serializeVenta($venta),
            'factura' => $this->serializeFactura($venta),
        ], 201);
    }

    /**
     * Factura detallada de una venta (para ver/imprimir desde el historial).
     */
    public function factura(Request $request, Venta $venta): JsonResponse
    {
        /** @var Usuario $cajero */
        $cajero = $request->user();

        // Solo el cajero dueño de la venta puede consultar su factura.
        Gate::forUser($cajero)->authorize('ver', $venta);

        return response()->json([
            'data' => $this->serializeFactura($venta),
        ]);
    }

    public function perfil(Request $request): JsonResponse
    {
        /** @var Usuario $cajero */
        $cajero = $request->user();
        $cajero->loadMissing('cargo:idCargo,nombre');

        return response()->json([
            'data' => [
                'idUsuario' => $cajero->idUsuario,
                'nombre' => $cajero->nombre,
                'apellido' => $cajero->apellido,
                'cedula' => $cajero->cedula,
                'telefono' => $cajero->telefono,
                'correo' => $cajero->correo,
                'rol' => $cajero->cargo?->nombre,
                'activo' => (bool) $cajero->activo,
                'creado_en' => $cajero->creado_en?->toIso8601String(),
            ],
        ]);
    }

    private const RESERVA_SLOT_MINUTES = 90;

    public function reservas(Request $request): JsonResponse
    {
        $data = $request->validate([
            'filtro' => ['nullable', 'string', 'in:proximas,hoy,todas'],
            'nombre' => ['nullable', 'string', 'max:120'],
            'fecha' => ['nullable', 'date'],
            'hora' => ['nullable', 'date_format:H:i'],
        ]);

        $filtro = $data['filtro'] ?? 'proximas';

        $query = Reserva::query()
            ->with([
                'mesa:idMesa,numero,nombre,capacidad',
                'cliente:idUsuario,nombre,apellido,correo,telefono',
            ]);

        if ($filtro === 'hoy') {
            $query
                ->whereIn('estado', ['CONFIRMADA', 'SOLICITADA'])
                ->whereDate('fecha_hora', now()->toDateString());
        } elseif ($filtro === 'todas') {
            $query->whereIn('estado', ['CONFIRMADA', 'SOLICITADA', 'COMPLETADA', 'NO_ASISTIO', 'CANCELADA']);
        } else {
            $query
                ->whereIn('estado', ['CONFIRMADA', 'SOLICITADA'])
                ->where('fecha_hora', '>=', now()->startOfDay());
        }

        if (! empty($data['fecha'])) {
            $query->whereDate('fecha_hora', $data['fecha']);
        }

        if (! empty($data['hora'])) {
            [$hora, $minuto] = array_pad(explode(':', $data['hora']), 2, '0');
            $query->whereRaw('HOUR(fecha_hora) = ? AND MINUTE(fecha_hora) = ?', [(int) $hora, (int) $minuto]);
        }

        if (! empty($data['nombre'])) {
            $nombre = $data['nombre'];
            $query->whereHas('cliente', function ($q) use ($nombre) {
                $q->where('nombre', 'like', "%{$nombre}%")
                    ->orWhere('apellido', 'like', "%{$nombre}%")
                    ->orWhere('correo', 'like', "%{$nombre}%")
                    ->orWhere('telefono', 'like', "%{$nombre}%");
            });
        }

        $items = $query
            ->orderBy('fecha_hora')
            ->limit(200)
            ->get();

        return response()->json([
            'filtro' => $filtro,
            'data' => $items->map(fn (Reserva $r) => $this->serializeReserva($r)),
        ]);
    }

    public function llamarMesero(Request $request): JsonResponse
    {
        /** @var Usuario $cajero */
        $cajero = $request->user();

        $pendiente = CocinaLlamadaMesero::query()
            ->whereNull('atendida_en')
            ->where('creado_en', '>=', now()->subMinutes(10))
            ->exists();

        if ($pendiente) {
            return response()->json([
                'message' => 'Ya hay una llamada activa al mesero. Espera a que atiendan.',
            ], 422);
        }

        $llamada = CocinaLlamadaMesero::create([
            'cajero_idUsuario' => (int) $cajero->getAuthIdentifier(),
            'creado_en' => now(),
            'atendida_en' => null,
            'mesero_idUsuario' => null,
        ]);

        $llamada->load('cajero:idUsuario,nombre,apellido');

        return response()->json([
            'message' => 'Llamada enviada al mesero.',
            'data' => $this->serializeLlamada($llamada),
        ], 201);
    }

    public function mesas(): JsonResponse
    {
        $mesas = Mesa::query()
            ->where('activa', true)
            ->orderBy('numero')
            ->get();

        $libres = $mesas->where('estado', 'LIBRE')->count();
        $ocupadas = $mesas->where('estado', 'OCUPADA')->count();

        return response()->json([
            'resumen' => [
                'total' => $mesas->count(),
                'libres' => $libres,
                'ocupadas' => $ocupadas,
            ],
            'data' => $mesas->map(fn (Mesa $mesa) => [
                'idMesa' => $mesa->idMesa,
                'numero' => $mesa->numero,
                'nombre' => $mesa->nombre,
                'capacidad' => $mesa->capacidad,
                'estado' => $mesa->estado,
                'disponible' => $mesa->estado === 'LIBRE',
            ]),
        ]);
    }

    public function ventas(Request $request): JsonResponse
    {
        /** @var Usuario $cajero */
        $cajero = $request->user();

        $data = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'hora_desde' => ['nullable', 'date_format:H:i'],
            'hora_hasta' => ['nullable', 'date_format:H:i'],
            'nombre' => ['nullable', 'string', 'max:120'],
            'producto' => ['nullable', 'string', 'max:120'],
            'numero' => ['nullable', 'string', 'max:40'],
            'metodo' => ['nullable', 'string', 'in:EFECTIVO,TARJETA,NEQUI,DAVIPLATA'],
        ]);

        $desde = $data['desde'] ?? now()->toDateString();
        $hasta = $data['hasta'] ?? now()->toDateString();

        $query = Venta::query()
            ->with([
                'pagos',
                'pedido.mesa:idMesa,numero,nombre',
                'pedido.mesero:idUsuario,nombre,apellido',
                'pedido.detalles' => fn ($q) => $q
                    ->where('estado_item', '!=', 'CANCELADO')
                    ->orderBy('idPedidoDetalle')
                    ->with('producto:idProducto,nombreProducto'),
            ])
            ->where('cajero_idUsuario', $cajero->idUsuario)
            ->whereDate('registrada_en', '>=', $desde)
            ->whereDate('registrada_en', '<=', $hasta)
            ->orderByDesc('registrada_en');

        if (! empty($data['nombre'])) {
            $nombre = $data['nombre'];
            $query->whereHas('pedido', function ($q) use ($nombre) {
                $q->where(function ($inner) use ($nombre) {
                    $inner->whereHas('mesero', function ($mq) use ($nombre) {
                        $mq->where('nombre', 'like', "%{$nombre}%")
                            ->orWhere('apellido', 'like', "%{$nombre}%");
                    })->orWhereHas('mesa', function ($msq) use ($nombre) {
                        $msq->where('nombre', 'like', "%{$nombre}%");
                    });
                });
            });
        }

        if (! empty($data['producto'])) {
            $producto = $data['producto'];
            $query->whereHas('pedido.detalles.producto', function ($q) use ($producto) {
                $q->where('nombreProducto', 'like', "%{$producto}%");
            });
        }

        if (! empty($data['metodo'])) {
            $metodo = $data['metodo'];
            $query->whereHas('pagos', function ($q) use ($metodo) {
                $q->where('metodo', $metodo);
            });
        }

        if (! empty($data['numero'])) {
            $numero = $data['numero'];
            $query->where('numero_factura', 'like', "%{$numero}%");
        }

        if (! empty($data['hora_desde'])) {
            $query->whereRaw('TIME(registrada_en) >= ?', [$data['hora_desde'].':00']);
        }

        if (! empty($data['hora_hasta'])) {
            $query->whereRaw('TIME(registrada_en) <= ?', [$data['hora_hasta'].':59']);
        }

        $ventas = $query->get();
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

    public function cancelarVenta(Request $request, Venta $venta): JsonResponse
    {
        /** @var Usuario $cajero */
        $cajero = $request->user();

        // Autorización centralizada en VentaPolicy (cajero dueño de la venta).
        Gate::forUser($cajero)->authorize('cancelar', $venta);

        if ($venta->estado === 'CANCELADA') {
            return response()->json(['message' => 'Esta venta ya fue cancelada.'], 422);
        }

        $data = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $ahora = now();
        $venta->estado = 'CANCELADA';
        $venta->motivo_cancelacion = trim($data['motivo']);
        $venta->cancelada_en = $ahora;
        $venta->cancelada_por_idUsuario = $cajero->idUsuario;
        $venta->admin_visto = false;
        $venta->save();

        $venta->load([
            'pagos',
            'pedido.mesa:idMesa,numero,nombre',
            'pedido.mesero:idUsuario,nombre,apellido',
            'pedido.detalles' => fn ($q) => $q
                ->where('estado_item', '!=', 'CANCELADO')
                ->orderBy('idPedidoDetalle')
                ->with('producto:idProducto,nombreProducto'),
        ]);

        return response()->json([
            'message' => 'Venta cancelada. El administrador fue notificado.',
            'data' => $this->serializeVenta($venta),
        ]);
    }

    private function pedidoListoParaCobro(Pedido $pedido): bool
    {
        if ($pedido->detalles->isEmpty()) {
            return false;
        }

        return $pedido->detalles
            ->where('estado_item', '!=', 'CANCELADO')
            ->every(fn ($d) => $d->estado_item === 'LISTO');
    }

    private function calcularSubtotal(Pedido $pedido): float
    {
        return (float) $pedido->detalles
            ->where('estado_item', '!=', 'CANCELADO')
            ->sum(fn ($d) => (float) $d->precio_unitario * (int) $d->cantidad);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCuentaResumen(Pedido $pedido): array
    {
        $subtotal = $this->calcularSubtotal($pedido);
        $lineas = $pedido->detalles->where('estado_item', '!=', 'CANCELADO');
        $unidades = (int) $lineas->sum('cantidad');

        return [
            'idPedido' => $pedido->idPedido,
            'estado' => $pedido->estado,
            'creado_en' => $pedido->creado_en?->toIso8601String(),
            'actualizado_en' => $pedido->actualizado_en?->toIso8601String(),
            'enviado_caja_en' => $pedido->enviado_caja_en?->toIso8601String(),
            'listo_para_cobro' => $this->pedidoListoParaCobro($pedido),
            'subtotal' => round($subtotal, 2),
            'num_lineas' => $lineas->count(),
            'total_unidades' => $unidades,
            'mesa' => $pedido->mesa ? [
                'idMesa' => $pedido->mesa->idMesa,
                'numero' => $pedido->mesa->numero,
                'nombre' => $pedido->mesa->nombre,
            ] : null,
            'mesero' => $pedido->mesero ? [
                'idUsuario' => $pedido->mesero->idUsuario,
                'nombre' => $pedido->mesero->nombre,
                'apellido' => $pedido->mesero->apellido,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCuentaDetalle(Pedido $pedido): array
    {
        $resumen = $this->serializeCuentaResumen($pedido);

        $resumen['detalles'] = $pedido->detalles->map(fn ($d) => [
            'idPedidoDetalle' => $d->idPedidoDetalle,
            'cantidad' => $d->cantidad,
            'precio_unitario' => $d->precio_unitario,
            'nota' => $d->nota,
            'estado_item' => $d->estado_item,
            'producto' => $d->producto ? [
                'nombreProducto' => $d->producto->nombreProducto,
                'tipo' => $d->producto->tipo,
            ] : null,
        ]);

        return $resumen;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeVenta(Venta $venta): array
    {
        $pedido = $venta->pedido;

        return [
            'idVenta' => $venta->idVenta,
            'numero_factura' => $venta->numero_factura,
            'estado' => $venta->estado ?? 'ACTIVA',
            'subtotal' => $venta->subtotal,
            'impuesto_o_servicio' => $venta->impuesto_o_servicio,
            'total' => $venta->total,
            'recibido' => $venta->recibido,
            'cambio' => $venta->cambio,
            'registrada_en' => $venta->registrada_en?->toIso8601String(),
            'motivo_cancelacion' => $venta->motivo_cancelacion,
            'cancelada_en' => $venta->cancelada_en?->toIso8601String(),
            'pagos' => $venta->pagos->map(fn (Pago $p) => [
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

    /**
     * Factura completa lista para mostrar/imprimir (incluye datos del local).
     *
     * @return array<string, mixed>
     */
    private function serializeFactura(Venta $venta): array
    {
        $venta->loadMissing([
            'pagos',
            'cajero:idUsuario,nombre,apellido',
            'pedido.mesa:idMesa,numero,nombre',
            'pedido.mesero:idUsuario,nombre,apellido',
            'pedido.detalles' => fn ($q) => $q
                ->where('estado_item', '!=', 'CANCELADO')
                ->orderBy('idPedidoDetalle')
                ->with('producto:idProducto,nombreProducto'),
        ]);

        $config = RestauranteConfig::query()->first();
        $pedido = $venta->pedido;
        $cajero = $venta->cajero;

        $items = $pedido && $pedido->relationLoaded('detalles')
            ? $pedido->detalles->map(function ($d) {
                $cantidad = (int) $d->cantidad;
                $precio = (float) $d->precio_unitario;

                return [
                    'idPedidoDetalle' => $d->idPedidoDetalle,
                    'nombreProducto' => $d->producto?->nombreProducto ?? 'Ítem',
                    'cantidad' => $cantidad,
                    'precio_unitario' => round($precio, 2),
                    'importe' => round($precio * $cantidad, 2),
                    'nota' => $d->nota,
                ];
            })->values()
            : collect();

        return [
            'idVenta' => $venta->idVenta,
            'numero_factura' => $venta->numero_factura,
            'estado' => $venta->estado ?? 'ACTIVA',
            'registrada_en' => $venta->registrada_en?->toIso8601String(),
            'subtotal' => $venta->subtotal,
            'impuesto_o_servicio' => $venta->impuesto_o_servicio,
            'total' => $venta->total,
            'recibido' => $venta->recibido,
            'cambio' => $venta->cambio,
            'motivo_cancelacion' => $venta->motivo_cancelacion,
            'cancelada_en' => $venta->cancelada_en?->toIso8601String(),
            'restaurante' => [
                'nombre_comercial' => $config?->nombre_comercial ?? 'Restaurante',
                'nit_o_documento' => $config?->nit_o_documento,
                'telefono' => $config?->telefono,
                'direccion' => $config?->direccion,
                'logo_url' => $config?->logo_url,
            ],
            'cajero' => $cajero ? [
                'idUsuario' => $cajero->idUsuario,
                'nombre' => $cajero->nombre,
                'apellido' => $cajero->apellido,
            ] : null,
            'mesa' => $pedido?->mesa ? [
                'idMesa' => $pedido->mesa->idMesa,
                'numero' => $pedido->mesa->numero,
                'nombre' => $pedido->mesa->nombre,
            ] : null,
            'mesero' => $pedido?->mesero ? [
                'nombre' => $pedido->mesero->nombre,
                'apellido' => $pedido->mesero->apellido,
            ] : null,
            'pedido' => $pedido ? [
                'idPedido' => $pedido->idPedido,
            ] : null,
            'items' => $items,
            'pagos' => $venta->pagos->map(fn (Pago $p) => [
                'idPago' => $p->idPago,
                'metodo' => $p->metodo,
                'valor' => $p->valor,
                'referencia' => $p->referencia,
                'pagado_en' => $p->pagado_en?->toIso8601String(),
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeReserva(Reserva $r): array
    {
        /** @var \Carbon\Carbon|null $fh */
        $fh = $r->fecha_hora;
        /** @var \Carbon\Carbon|null $creado */
        $creado = $r->creado_en;

        $fin = $fh ? $fh->copy()->addMinutes(self::RESERVA_SLOT_MINUTES) : null;
        $cliente = $r->cliente;
        $nombre = $cliente ? trim((string) ($cliente->nombre ?? '')) : '';
        $apellido = $cliente ? trim((string) ($cliente->apellido ?? '')) : '';
        $nombreCompleto = trim($nombre.' '.$apellido) ?: ($cliente?->correo ?? '—');

        return [
            'idReserva' => $r->idReserva,
            'reservado_por' => $nombreCompleto,
            'fecha_hora' => $fh ? $fh->timezone(config('app.timezone'))->format(\DateTime::ATOM) : null,
            'fecha_hora_fin' => $fin ? $fin->timezone(config('app.timezone'))->format(\DateTime::ATOM) : null,
            'num_personas' => $r->num_personas,
            'estado' => $r->estado,
            'notas' => $r->notas,
            'motivo_cancelacion' => $r->motivo_cancelacion,
            'creado_en' => $creado ? $creado->timezone(config('app.timezone'))->format(\DateTime::ATOM) : null,
            'cliente' => $cliente ? [
                'idUsuario' => $cliente->idUsuario,
                'nombre' => $nombre,
                'apellido' => $apellido,
                'nombre_completo' => $nombreCompleto,
                'correo' => $cliente->correo,
                'telefono' => $cliente->telefono,
            ] : null,
            'mesa' => $r->mesa ? [
                'idMesa' => $r->mesa->idMesa,
                'numero' => $r->mesa->numero,
                'nombre' => $r->mesa->nombre,
                'capacidad' => $r->mesa->capacidad,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLlamada(CocinaLlamadaMesero $l): array
    {
        $cajero = $l->cajero;
        $nombreCajero = $cajero
            ? trim(($cajero->nombre ?? '').' '.($cajero->apellido ?? ''))
            : 'Caja';

        return [
            'id' => $l->id,
            'origen' => 'CAJERO',
            'creado_en' => $l->creado_en?->toIso8601String(),
            'atendida_en' => $l->atendida_en?->toIso8601String(),
            'solicitante_nombre' => $nombreCajero ?: 'Caja',
        ];
    }
}
