<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CocinaLlamadaMesero;
use App\Models\ProductoEstadoLog;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MeseroController extends Controller
{
    private const ABIERTOS = ['PENDIENTE', 'EN_PREPARACION', 'LISTO', 'ENTREGADO'];

    /**
     * Mesas activas con resumen del pedido abierto (si existe).
     */
    public function mesas(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof Usuario) {
            abort(401, 'No autenticado.');
        }

        $authId = (int) $user->getAuthIdentifier();

        $mesas = Mesa::query()
            ->where('activa', true)
            ->orderBy('numero')
            ->get();

        $pedidos = Pedido::query()
            ->whereIn('estado', self::ABIERTOS)
            ->with([
                'detalles' => function ($q) {
                    $q->orderBy('idPedidoDetalle')
                        ->with('producto:idProducto,nombreProducto');
                },
            ])
            ->get()
            ->keyBy('mesa_idMesa');

        return response()->json([
            'data' => $mesas->map(function (Mesa $mesa) use ($pedidos, $authId) {
                /** @var Pedido|null $p */
                $p = $pedidos->get($mesa->idMesa);

                // Mantener mesa alineada con pedidos abiertos (evita "Libre" con cuenta activa).
                if ($p && $mesa->estado !== 'OCUPADA') {
                    $mesa->estado = 'OCUPADA';
                    $mesa->save();
                }

                $pedidoActivo = null;
                if ($p) {
                    $lineas = $p->detalles;
                    $totalUnidades = (int) $lineas->sum('cantidad');
                    $numLineas = $lineas->count();

                    if ((int) $p->mesero_idUsuario === $authId) {
                        $subtotal = $lineas
                            ->where('estado_item', '!=', 'CANCELADO')
                            ->sum(fn ($d) => (float) $d->precio_unitario * (int) $d->cantidad);
                        $previewParts = $lineas->take(2)->map(function ($d) {
                            $name = $d->producto?->nombreProducto ?? 'Ítem';

                            return $name.' ×'.(int) $d->cantidad;
                        });
                        $preview = $previewParts->filter()->implode(' · ');
                        if ($numLineas > 2) {
                            $preview .= ' +'.($numLineas - 2).' más';
                        }

                        $pedidoActivo = [
                            'idPedido' => $p->idPedido,
                            'estado' => $p->estado,
                            'creado_en' => $p->creado_en?->toIso8601String(),
                            'notas_mesa' => $p->notas,
                            'num_lineas' => $numLineas,
                            'total_unidades' => $totalUnidades,
                            'subtotal_cop' => (int) round($subtotal),
                            'resumen_productos' => $preview !== '' ? $preview : null,
                        ];
                    } else {
                        $pedidoActivo = [
                            'bloqueado' => true,
                            'estado' => $p->estado,
                            'mensaje' => 'Pedido abierto por otro mesero.',
                            'num_lineas' => $numLineas,
                            'total_unidades' => $totalUnidades,
                        ];
                    }
                }

                return [
                    'idMesa' => $mesa->idMesa,
                    'numero' => $mesa->numero,
                    'nombre' => $mesa->nombre,
                    'capacidad' => $mesa->capacidad,
                    'estado' => $p ? 'OCUPADA' : $mesa->estado,
                    'pedido_activo' => $pedidoActivo,
                ];
            }),
        ]);
    }

    /**
     * Alertas: pedidos listos + llamadas de cocina.
     */
    public function alertas(Request $request): JsonResponse
    {
        $listos = $this->pedidosListosData($request);

        $llamadas = CocinaLlamadaMesero::query()
            ->with([
                'cocinero:idUsuario,nombre,apellido',
                'cajero:idUsuario,nombre,apellido',
            ])
            ->whereNull('atendida_en')
            ->where('creado_en', '>=', now()->subHours(4))
            ->orderByDesc('creado_en')
            ->get();

        $cambiosMenu = ProductoEstadoLog::query()
            ->with([
                'producto:idProducto,nombreProducto',
                'usuario:idUsuario,nombre,apellido',
            ])
            ->whereNull('atendida_en')
            ->where('creado_en', '>=', now()->subHours(24))
            ->orderByDesc('creado_en')
            ->get();

        return response()->json([
            'pedidos_listos' => $listos,
            'llamadas_cocina' => $llamadas->map(fn (CocinaLlamadaMesero $l) => [
                'id' => $l->id,
                'creado_en' => $l->creado_en?->toIso8601String(),
                'origen' => $l->cajero_idUsuario ? 'CAJERO' : 'COCINA',
                'cocinero_nombre' => $l->cajero_idUsuario
                    ? (trim(($l->cajero->nombre ?? '').' '.($l->cajero->apellido ?? '')) ?: 'Caja')
                    : (trim(($l->cocinero->nombre ?? '').' '.($l->cocinero->apellido ?? '')) ?: 'Cocina'),
                'solicitante_nombre' => $l->cajero_idUsuario
                    ? (trim(($l->cajero->nombre ?? '').' '.($l->cajero->apellido ?? '')) ?: 'Caja')
                    : (trim(($l->cocinero->nombre ?? '').' '.($l->cocinero->apellido ?? '')) ?: 'Cocina'),
            ]),
            'cambios_menu' => $cambiosMenu->map(fn (ProductoEstadoLog $log) => [
                'id' => $log->idLog,
                'idProducto' => $log->producto_idProducto,
                'nombreProducto' => $log->producto?->nombreProducto ?? 'Plato',
                'activo' => (bool) $log->activo,
                'creado_en' => $log->creado_en?->toIso8601String(),
                'usuario_nombre' => $log->usuario
                    ? trim($log->usuario->nombre.' '.$log->usuario->apellido)
                    : 'Cocina',
            ]),
        ]);
    }

    public function atenderCambioMenu(Request $request, ProductoEstadoLog $log): JsonResponse
    {
        $meseroId = (int) $request->user()->getAuthIdentifier();

        if ($log->atendida_en) {
            return response()->json(['message' => 'Este aviso ya fue atendido.'], 422);
        }

        $log->atendida_en = now();
        $log->mesero_atendio_idUsuario = $meseroId;
        $log->save();

        return response()->json([
            'message' => 'Aviso de menú atendido.',
        ]);
    }

    public function atenderLlamadaCocina(Request $request, CocinaLlamadaMesero $llamada): JsonResponse
    {
        $meseroId = (int) $request->user()->getAuthIdentifier();

        if ($llamada->atendida_en) {
            return response()->json(['message' => 'Esta llamada ya fue atendida.'], 422);
        }

        $llamada->atendida_en = now();
        $llamada->mesero_idUsuario = $meseroId;
        $llamada->save();

        return response()->json([
            'message' => 'Llamada de cocina atendida.',
        ]);
    }

    /**
     * Pedidos marcados listos en cocina, pendientes de retirar por el mesero.
     *
     * @return array<string, mixed>
     */
    private function pedidosListosData(Request $request): array
    {
        $user = $request->user();
        if (! $user instanceof Usuario) {
            abort(401, 'No autenticado.');
        }

        $authId = (int) $user->getAuthIdentifier();

        $pedidos = Pedido::query()
            ->with(['mesa:idMesa,numero,nombre'])
            ->where('mesero_idUsuario', $authId)
            ->where('estado', 'LISTO')
            ->orderBy('actualizado_en')
            ->get();

        return [
            'total' => $pedidos->count(),
            'data' => $pedidos->map(fn (Pedido $p) => [
                'idPedido' => $p->idPedido,
                'estado' => $p->estado,
                'actualizado_en' => $p->actualizado_en?->toIso8601String(),
                'creado_en' => $p->creado_en?->toIso8601String(),
                'mesa' => $p->mesa ? [
                    'idMesa' => $p->mesa->idMesa,
                    'numero' => $p->mesa->numero,
                    'nombre' => $p->mesa->nombre,
                ] : null,
            ]),
        ];
    }

    public function showPedido(Request $request, Pedido $pedido): JsonResponse
    {
        $this->authorizeMesero($request, $pedido);

        $pedido->load([
            'mesa:idMesa,numero,nombre',
            'detalles' => fn ($q) => $q->orderBy('idPedidoDetalle')->with('producto:idProducto,nombreProducto,tipo'),
        ]);

        return response()->json([
            'data' => $this->serializePedidoCompleto($pedido),
        ]);
    }

    /**
     * Abrir cuenta: nuevo pedido en la mesa (solo si no hay otro abierto).
     */
    public function storePedido(Request $request): JsonResponse
    {
        $mesero = $request->user();
        if (! $mesero instanceof Usuario) {
            abort(401, 'No autenticado.');
        }

        $data = $request->validate([
            'mesa_idMesa' => ['required', 'integer', 'exists:mesa,idMesa'],
            'notas' => ['nullable', 'string', 'max:500'],
            'reserva_idReserva' => ['nullable', 'integer', 'exists:reserva,idReserva'],
        ]);

        $mesa = Mesa::query()->where('idMesa', $data['mesa_idMesa'])->where('activa', true)->first();
        if (! $mesa) {
            abort(404, 'Mesa no disponible.');
        }

        $existe = Pedido::query()
            ->where('mesa_idMesa', $mesa->idMesa)
            ->whereIn('estado', self::ABIERTOS)
            ->exists();

        if ($existe) {
            return response()->json([
                'message' => 'Esta mesa ya tiene un pedido abierto. Ciérralo o úsalo desde el panel.',
            ], 409);
        }

        $pedido = DB::transaction(function () use ($data, $mesero, $mesa): Pedido {
            $mesa->estado = 'OCUPADA';
            $mesa->save();

            return Pedido::create([
                'mesa_idMesa' => $mesa->idMesa,
                'mesero_idUsuario' => (int) $mesero->getAuthIdentifier(),
                'reserva_idReserva' => $data['reserva_idReserva'] ?? null,
                'estado' => 'PENDIENTE',
                'notas' => $data['notas'] ?? null,
                'creado_en' => now(),
                'actualizado_en' => now(),
                'cerrado_en' => null,
            ]);
        });

        $pedido->load([
            'mesa:idMesa,numero,nombre',
            'detalles' => fn ($q) => $q->orderBy('idPedidoDetalle')->with('producto:idProducto,nombreProducto,tipo'),
        ]);

        return response()->json([
            'data' => $this->serializePedidoCompleto($pedido),
        ], 201);
    }

    /**
     * Agregar línea al pedido (producto activo, precio snapshot).
     */
    public function storeDetalle(Request $request, Pedido $pedido): JsonResponse
    {
        $this->authorizeMesero($request, $pedido);

        if (in_array($pedido->estado, ['CERRADO', 'CANCELADO'], true)) {
            return response()->json([
                'message' => 'No se pueden agregar ítems: la cuenta ya está cerrada.',
            ], 422);
        }

        $data = $request->validate([
            'producto_idProducto' => ['required', 'integer', 'exists:producto,idProducto'],
            'cantidad' => ['required', 'integer', 'min:1', 'max:99'],
            'nota' => ['nullable', 'string', 'max:255'],
        ]);

        $producto = Producto::query()
            ->where('idProducto', $data['producto_idProducto'])
            ->where('activo', true)
            ->whereHas('categoria', fn ($q) => $q->where('activa', true))
            ->first();

        if (! $producto) {
            return response()->json(['message' => 'Producto no disponible.'], 422);
        }

        $detalle = PedidoDetalle::create([
            'pedido_idPedido' => $pedido->idPedido,
            'producto_idProducto' => $producto->idProducto,
            'cantidad' => $data['cantidad'],
            'precio_unitario' => $producto->precio,
            'nota' => $data['nota'] ?? null,
            'estado_item' => 'PENDIENTE',
            'creado_en' => now(),
        ]);

        // Nuevos ítems en una cuenta ya lista o recibida → vuelven a cocina como pedido pendiente.
        if (in_array($pedido->estado, ['LISTO', 'ENTREGADO'], true)) {
            $pedido->estado = 'PENDIENTE';
            $pedido->save();
        } else {
            $pedido->touch();
        }

        $mesa = Mesa::query()->where('idMesa', $pedido->mesa_idMesa)->first();
        if ($mesa && $mesa->estado !== 'OCUPADA') {
            $mesa->estado = 'OCUPADA';
            $mesa->save();
        }

        $detalle->load('producto:idProducto,nombreProducto,tipo');

        return response()->json([
            'data' => [
                'detalle' => [
                    'idPedidoDetalle' => $detalle->idPedidoDetalle,
                    'cantidad' => $detalle->cantidad,
                    'precio_unitario' => $detalle->precio_unitario,
                    'nota' => $detalle->nota,
                    'estado_item' => $detalle->estado_item,
                    'producto' => $detalle->producto ? [
                        'nombreProducto' => $detalle->producto->nombreProducto,
                        'tipo' => $detalle->producto->tipo,
                    ] : null,
                ],
                'pedido' => $this->serializePedidoCompleto($pedido->refresh()->load([
                    'mesa:idMesa,numero,nombre',
                    'detalles' => fn ($q) => $q->orderBy('idPedidoDetalle')->with('producto:idProducto,nombreProducto,tipo'),
                ])),
            ],
        ], 201);
    }

    /**
     * Mesero retira el pedido de cocina: deja de aparecer en cola del cocinero.
     */
    public function recibirPedido(Request $request, Pedido $pedido): JsonResponse
    {
        $this->authorizeMesero($request, $pedido);

        if ($pedido->estado !== 'LISTO') {
            return response()->json([
                'message' => 'Este pedido no está en espera de retiro en cocina.',
            ], 422);
        }

        $pedido->estado = 'ENTREGADO';
        $pedido->save();

        $mesa = Mesa::query()->where('idMesa', $pedido->mesa_idMesa)->first();
        if ($mesa && $mesa->estado !== 'OCUPADA') {
            $mesa->estado = 'OCUPADA';
            $mesa->save();
        }

        $pedido->load([
            'mesa:idMesa,numero,nombre',
            'detalles' => fn ($q) => $q->orderBy('idPedidoDetalle')->with('producto:idProducto,nombreProducto,tipo'),
        ]);

        return response()->json([
            'message' => 'Pedido recibido. Ya puedes servirlo en la mesa.',
            'data' => $this->serializePedidoCompleto($pedido),
        ]);
    }

    /**
     * Mesero envía la cuenta a caja para que el cajero la cobre.
     * Puede reenviarse para reflejar cambios (platos agregados o eliminados).
     */
    public function enviarACaja(Request $request, Pedido $pedido): JsonResponse
    {
        $this->authorizeMesero($request, $pedido);

        if (in_array($pedido->estado, ['CERRADO', 'CANCELADO'], true)) {
            return response()->json([
                'message' => 'Este pedido ya está cerrado o cancelado.',
            ], 422);
        }

        $tieneItems = $pedido->detalles()
            ->where('estado_item', '!=', 'CANCELADO')
            ->exists();

        if (! $tieneItems) {
            return response()->json([
                'message' => 'Agrega al menos un plato antes de enviar la cuenta a caja.',
            ], 422);
        }

        $reenvio = $pedido->enviado_caja_en !== null;

        $pedido->enviado_caja_en = now();
        $pedido->actualizado_en = now();
        $pedido->save();

        $pedido->refresh()->load([
            'mesa:idMesa,numero,nombre',
            'detalles' => fn ($q) => $q->orderBy('idPedidoDetalle')->with('producto:idProducto,nombreProducto,tipo'),
        ]);

        return response()->json([
            'data' => $this->serializePedidoCompleto($pedido),
            'message' => $reenvio
                ? 'Cuenta actualizada en caja. El cajero verá los cambios.'
                : 'Cuenta enviada a caja. El cajero ya puede cobrarla.',
        ]);
    }

    /**
     * Cancelar pedido abierto (cliente se va, error, etc.) y liberar la mesa.
     */
    public function cancelarPedido(Request $request, Pedido $pedido): JsonResponse
    {
        $this->authorizeMesero($request, $pedido);

        if (in_array($pedido->estado, ['CERRADO', 'CANCELADO'], true)) {
            return response()->json([
                'message' => 'Este pedido ya está cerrado o cancelado.',
            ], 422);
        }

        $data = $request->validate([
            'motivo' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        DB::transaction(function () use ($pedido, $data): void {
            $pedido->estado = 'CANCELADO';
            $pedido->motivo_cancelacion = trim($data['motivo']);
            $pedido->cancelado_en = now();
            $pedido->actualizado_en = now();
            $pedido->save();

            $pedido->detalles()
                ->where('estado_item', '!=', 'CANCELADO')
                ->update(['estado_item' => 'CANCELADO']);

            $mesa = Mesa::query()->where('idMesa', $pedido->mesa_idMesa)->first();
            if ($mesa) {
                $mesa->estado = 'LIBRE';
                $mesa->save();
            }
        });

        $pedido->refresh()->load([
            'mesa:idMesa,numero,nombre',
            'detalles' => fn ($q) => $q->orderBy('idPedidoDetalle')->with('producto:idProducto,nombreProducto,tipo'),
        ]);

        return response()->json([
            'data' => $this->serializePedidoCompleto($pedido),
            'message' => 'Pedido cancelado. Cocina fue notificada y la mesa quedó libre.',
        ]);
    }

    /**
     * Perfil del mesero en sesión (solo lectura).
     */
    public function perfil(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof Usuario) {
            abort(401, 'No autenticado.');
        }

        $user->loadMissing('cargo:idCargo,nombre');

        return response()->json([
            'data' => [
                'idUsuario' => $user->idUsuario,
                'nombre' => $user->nombre,
                'apellido' => $user->apellido,
                'cedula' => $user->cedula,
                'telefono' => $user->telefono,
                'correo' => $user->correo,
                'rol' => $user->cargo?->nombre,
                'activo' => (bool) $user->activo,
                'creado_en' => $user->creado_en?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Historial de pedidos tomados por el mesero en sesión.
     */
    public function historialPedidos(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof Usuario) {
            abort(401, 'No autenticado.');
        }

        $data = $request->validate([
            'estado' => ['nullable', 'string', 'in:todas,PENDIENTE,EN_PREPARACION,LISTO,ENTREGADO,CERRADO,CANCELADO'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'orden' => ['nullable', 'string', 'in:reciente,antiguo'],
        ]);

        $estado = $data['estado'] ?? 'todas';
        $orden = $data['orden'] ?? 'reciente';

        $query = Pedido::query()
            ->with([
                'mesa:idMesa,numero,nombre',
                'detalles' => fn ($q) => $q->with('producto:idProducto,nombreProducto'),
            ])
            ->where('mesero_idUsuario', (int) $user->getAuthIdentifier());

        if ($estado !== 'todas') {
            $query->where('estado', $estado);
        }
        if (! empty($data['desde'])) {
            $query->whereDate('creado_en', '>=', $data['desde']);
        }
        if (! empty($data['hasta'])) {
            $query->whereDate('creado_en', '<=', $data['hasta']);
        }

        $query->orderBy('creado_en', $orden === 'antiguo' ? 'asc' : 'desc');

        $items = $query->limit(200)->get();

        return response()->json([
            'data' => $items->map(fn (Pedido $p) => $this->serializePedidoHistorial($p)),
            'total' => $items->count(),
        ]);
    }

    private function authorizeMesero(Request $request, Pedido $pedido): void
    {
        $user = $request->user();
        if (! $user instanceof Usuario) {
            abort(401, 'No autenticado.');
        }

        // Autorización centralizada en PedidoPolicy (dueño del pedido).
        Gate::forUser($user)->authorize('gestionar', $pedido);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePedidoHistorial(Pedido $p): array
    {
        $lineas = $p->detalles;
        $subtotal = $lineas
            ->where('estado_item', '!=', 'CANCELADO')
            ->sum(fn ($d) => (float) $d->precio_unitario * (int) $d->cantidad);

        return [
            'idPedido' => $p->idPedido,
            'estado' => $p->estado,
            'notas' => $p->notas,
            'motivo_cancelacion' => $p->motivo_cancelacion,
            'creado_en' => $p->creado_en?->toIso8601String(),
            'cerrado_en' => $p->cerrado_en?->toIso8601String(),
            'cancelado_en' => $p->cancelado_en?->toIso8601String(),
            'mesa' => $p->mesa ? [
                'numero' => $p->mesa->numero,
                'nombre' => $p->mesa->nombre,
            ] : null,
            'num_lineas' => $lineas->count(),
            'total_unidades' => (int) $lineas->sum('cantidad'),
            'subtotal_cop' => (int) round($subtotal),
            'resumen_productos' => $lineas->take(3)->map(function ($d) {
                $name = $d->producto?->nombreProducto ?? 'Ítem';

                return $name.' ×'.(int) $d->cantidad;
            })->implode(' · '),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePedidoCompleto(Pedido $pedido): array
    {
        return [
            'idPedido' => $pedido->idPedido,
            'estado' => $pedido->estado,
            'notas' => $pedido->notas,
            'motivo_cancelacion' => $pedido->motivo_cancelacion,
            'cancelado_en' => $pedido->cancelado_en?->toIso8601String(),
            'enviado_caja_en' => $pedido->enviado_caja_en?->toIso8601String(),
            'creado_en' => $pedido->creado_en?->toIso8601String(),
            'actualizado_en' => $pedido->actualizado_en?->toIso8601String(),
            'mesa' => $pedido->mesa ? [
                'idMesa' => $pedido->mesa->idMesa,
                'numero' => $pedido->mesa->numero,
                'nombre' => $pedido->mesa->nombre,
            ] : null,
            'detalles' => $pedido->detalles->map(fn ($d) => [
                'idPedidoDetalle' => $d->idPedidoDetalle,
                'cantidad' => $d->cantidad,
                'precio_unitario' => $d->precio_unitario,
                'nota' => $d->nota,
                'estado_item' => $d->estado_item,
                'motivo_cancelacion' => $d->motivo_cancelacion,
                'cancelado_en' => $d->cancelado_en?->toIso8601String(),
                'producto' => $d->producto ? [
                    'nombreProducto' => $d->producto->nombreProducto,
                    'tipo' => $d->producto->tipo,
                ] : null,
            ]),
        ];
    }
}
