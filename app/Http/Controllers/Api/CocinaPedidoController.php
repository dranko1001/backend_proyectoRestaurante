<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CocinaLlamadaMesero;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CocinaPedidoController extends Controller
{
    /**
     * Pedidos visibles en cocina: los que el salón ya registró y aún no se cerraron.
     */
    public function index(): JsonResponse
    {
        $pedidos = Pedido::query()
            ->with([
                'mesa:idMesa,numero,nombre',
                'mesero:idUsuario,nombre,apellido',
                'detalles' => fn ($q) => $q->orderBy('idPedidoDetalle')->with([
                    'producto:idProducto,nombreProducto,tipo,descripcion,imagen',
                    'canceladoPor:idUsuario,nombre,apellido',
                ]),
            ])
            ->whereIn('estado', ['PENDIENTE', 'EN_PREPARACION', 'LISTO'])
            ->whereHas('detalles', fn ($q) => $q->where('estado_item', '!=', 'CANCELADO'))
            ->orderByRaw("FIELD(estado, 'PENDIENTE', 'EN_PREPARACION', 'LISTO')")
            ->orderBy('creado_en')
            ->get();

        return response()->json([
            'data' => $pedidos->map(fn (Pedido $p) => $this->serializePedido($p)),
        ]);
    }

    /**
     * Historial y filtros para el panel de ajustes en cocina.
     */
    public function historial(Request $request): JsonResponse
    {
        $filtro = $request->query('filtro', 'todas');

        if ($filtro === 'platos_cancelados') {
            return $this->historialPlatosCancelados();
        }

        $query = Pedido::query()
            ->with([
                'mesa:idMesa,numero,nombre',
                'mesero:idUsuario,nombre,apellido',
                'detalles' => fn ($q) => $q->orderBy('idPedidoDetalle')->with([
                    'producto:idProducto,nombreProducto,tipo,descripcion,imagen',
                    'canceladoPor:idUsuario,nombre,apellido',
                ]),
            ]);

        match ($filtro) {
            'activos' => $query->whereIn('estado', ['PENDIENTE', 'EN_PREPARACION', 'LISTO']),
            'cancelados' => $query->where('estado', 'CANCELADO'),
            'cerrados' => $query->where('estado', 'CERRADO'),
            'entregados' => $query->where('estado', 'ENTREGADO'),
            'listos' => $query->where('estado', 'LISTO'),
            default => null,
        };

        $items = $query->orderByDesc('creado_en')->limit(150)->get();

        $conteos = [
            'todas' => (int) Pedido::query()->count(),
            'activos' => (int) Pedido::query()->whereIn('estado', ['PENDIENTE', 'EN_PREPARACION', 'LISTO'])->count(),
            'cancelados' => (int) Pedido::query()->where('estado', 'CANCELADO')->count(),
            'cerrados' => (int) Pedido::query()->where('estado', 'CERRADO')->count(),
            'entregados' => (int) Pedido::query()->where('estado', 'ENTREGADO')->count(),
            'listos' => (int) Pedido::query()->where('estado', 'LISTO')->count(),
            'platos_cancelados' => (int) PedidoDetalle::query()
                ->where('estado_item', 'CANCELADO')
                ->whereNotNull('motivo_cancelacion')
                ->count(),
        ];

        return response()->json([
            'data' => $items->map(fn (Pedido $p) => $this->serializePedido($p)),
            'conteos' => $conteos,
            'filtro' => $filtro,
        ]);
    }

    public function cancelarDetalle(Request $request, Pedido $pedido, PedidoDetalle $detalle): JsonResponse
    {
        if ((int) $detalle->pedido_idPedido !== (int) $pedido->idPedido) {
            abort(404, 'Ítem no pertenece a este pedido.');
        }

        if (in_array($pedido->estado, ['CERRADO', 'CANCELADO'], true)) {
            return response()->json(['message' => 'Este pedido ya no puede modificarse en cocina.'], 422);
        }

        if ($detalle->estado_item === 'CANCELADO') {
            return response()->json(['message' => 'Este plato ya está cancelado.'], 422);
        }

        $data = $request->validate([
            'motivo' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $user = $request->user();
        if (! $user instanceof Usuario) {
            abort(401, 'No autenticado.');
        }

        DB::transaction(function () use ($detalle, $data, $user): void {
            $detalle->estado_item = 'CANCELADO';
            $detalle->motivo_cancelacion = trim($data['motivo']);
            $detalle->cancelado_en = now();
            $detalle->cancelado_por_idUsuario = (int) $user->getAuthIdentifier();
            $detalle->save();
        });

        $pedido->load([
            'mesa:idMesa,numero,nombre',
            'mesero:idUsuario,nombre,apellido',
            'detalles' => fn ($q) => $q->orderBy('idPedidoDetalle')->with([
                'producto:idProducto,nombreProducto,tipo,descripcion,imagen',
                'canceladoPor:idUsuario,nombre,apellido',
            ]),
        ]);

        $activos = $pedido->detalles->where('estado_item', '!=', 'CANCELADO')->count();
        if ($activos === 0 && in_array($pedido->estado, ['PENDIENTE', 'EN_PREPARACION'], true)) {
            $pedido->estado = 'LISTO';
            $pedido->save();
        }

        return response()->json([
            'message' => 'Plato cancelado. El mesero verá el motivo en la cuenta.',
            'data' => $this->serializePedido($pedido),
        ]);
    }

    private function historialPlatosCancelados(): JsonResponse
    {
        $items = PedidoDetalle::query()
            ->with([
                'producto:idProducto,nombreProducto,tipo',
                'canceladoPor:idUsuario,nombre,apellido',
                'pedido' => fn ($q) => $q->with([
                    'mesa:idMesa,numero,nombre',
                    'mesero:idUsuario,nombre,apellido',
                ]),
            ])
            ->where('estado_item', 'CANCELADO')
            ->whereNotNull('motivo_cancelacion')
            ->orderByDesc('cancelado_en')
            ->limit(150)
            ->get();

        return response()->json([
            'data' => $items->map(fn (PedidoDetalle $d) => [
                'tipo' => 'plato_cancelado',
                'idPedidoDetalle' => $d->idPedidoDetalle,
                'idPedido' => $d->pedido_idPedido,
                'cantidad' => $d->cantidad,
                'motivo_cancelacion' => $d->motivo_cancelacion,
                'cancelado_en' => $d->cancelado_en?->toIso8601String(),
                'cancelado_por_nombre' => $d->canceladoPor
                    ? trim(($d->canceladoPor->nombre ?? '').' '.($d->canceladoPor->apellido ?? ''))
                    : 'Cocina',
                'producto' => $d->producto ? [
                    'nombreProducto' => $d->producto->nombreProducto,
                    'tipo' => $d->producto->tipo,
                ] : null,
                'mesa' => $d->pedido?->mesa ? [
                    'numero' => $d->pedido->mesa->numero,
                    'nombre' => $d->pedido->mesa->nombre,
                ] : null,
                'pedido_estado' => $d->pedido?->estado,
            ]),
            'conteos' => [
                'platos_cancelados' => $items->count(),
            ],
            'filtro' => 'platos_cancelados',
        ]);
    }

    public function llamarMesero(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof Usuario) {
            abort(401, 'No autenticado.');
        }

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
            'cocinero_idUsuario' => (int) $user->getAuthIdentifier(),
            'creado_en' => now(),
            'atendida_en' => null,
            'mesero_idUsuario' => null,
        ]);

        $llamada->load('cocinero:idUsuario,nombre,apellido');

        return response()->json([
            'message' => 'Llamada enviada al mesero.',
            'data' => $this->serializeLlamada($llamada),
        ], 201);
    }

    public function updateEstado(Request $request, Pedido $pedido): JsonResponse
    {
        $data = $request->validate([
            'estado' => ['required', 'string', Rule::in(['EN_PREPARACION', 'LISTO'])],
        ]);

        if (in_array($pedido->estado, ['CERRADO', 'CANCELADO'], true)) {
            throw ValidationException::withMessages([
                'estado' => ['Este pedido ya no puede modificarse en cocina.'],
            ]);
        }

        $next = $data['estado'];

        if ($pedido->estado === 'PENDIENTE' && $next !== 'EN_PREPARACION') {
            throw ValidationException::withMessages([
                'estado' => ['Un pedido nuevo debe pasar primero a EN_PREPARACION.'],
            ]);
        }

        if ($pedido->estado === 'EN_PREPARACION' && $next !== 'LISTO') {
            throw ValidationException::withMessages([
                'estado' => ['En preparación solo puede pasar a LISTO.'],
            ]);
        }

        if ($pedido->estado === 'LISTO') {
            throw ValidationException::withMessages([
                'estado' => ['El pedido ya está listo para que el mesero lo retire.'],
            ]);
        }

        DB::transaction(function () use ($pedido, $next): void {
            $pedido->estado = $next;
            $pedido->save();

            if ($next === 'EN_PREPARACION') {
                $pedido->detalles()->where('estado_item', 'PENDIENTE')->update(['estado_item' => 'EN_PREPARACION']);
            }
            if ($next === 'LISTO') {
                $pedido->detalles()->whereIn('estado_item', ['PENDIENTE', 'EN_PREPARACION'])->update(['estado_item' => 'LISTO']);
            }
        });

        $pedido->load([
            'mesa:idMesa,numero,nombre',
            'mesero:idUsuario,nombre,apellido',
            'detalles' => fn ($q) => $q->orderBy('idPedidoDetalle')->with([
                'producto:idProducto,nombreProducto,tipo,descripcion,imagen',
                'canceladoPor:idUsuario,nombre,apellido',
            ]),
        ]);

        return response()->json([
            'data' => $this->serializePedido($pedido),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePedido(Pedido $p): array
    {
        return [
            'idPedido' => $p->idPedido,
            'estado' => $p->estado,
            'notas' => $p->notas,
            'motivo_cancelacion' => $p->motivo_cancelacion,
            'cancelado_en' => $p->cancelado_en?->toIso8601String(),
            'cerrado_en' => $p->cerrado_en?->toIso8601String(),
            'creado_en' => $p->creado_en?->toIso8601String(),
            'actualizado_en' => $p->actualizado_en?->toIso8601String(),
            'mesa' => $p->mesa ? [
                'idMesa' => $p->mesa->idMesa,
                'numero' => $p->mesa->numero,
                'nombre' => $p->mesa->nombre,
            ] : null,
            'mesero' => $p->mesero ? [
                'idUsuario' => $p->mesero->idUsuario,
                'nombre' => trim(($p->mesero->nombre ?? '').' '.($p->mesero->apellido ?? '')) ?: null,
            ] : null,
            'detalles' => $p->detalles->map(fn ($d) => $this->serializeDetalle($d)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDetalle(PedidoDetalle $d): array
    {
        $cocinero = $d->canceladoPor;

        return [
            'idPedidoDetalle' => $d->idPedidoDetalle,
            'cantidad' => $d->cantidad,
            'precio_unitario' => $d->precio_unitario,
            'nota' => $d->nota,
            'estado_item' => $d->estado_item,
            'motivo_cancelacion' => $d->motivo_cancelacion,
            'cancelado_en' => $d->cancelado_en?->toIso8601String(),
            'cancelado_por_nombre' => $cocinero
                ? trim(($cocinero->nombre ?? '').' '.($cocinero->apellido ?? ''))
                : null,
            'producto' => $d->producto ? [
                'nombreProducto' => $d->producto->nombreProducto,
                'tipo' => $d->producto->tipo,
                'descripcion' => $d->producto->descripcion,
                'imagenUrl' => $d->producto->imagen
                    ? asset('storage/'.$d->producto->imagen)
                    : null,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLlamada(CocinaLlamadaMesero $l): array
    {
        $cocinero = $l->cocinero;
        $nombreCocinero = $cocinero
            ? trim(($cocinero->nombre ?? '').' '.($cocinero->apellido ?? ''))
            : 'Cocina';

        return [
            'id' => $l->id,
            'creado_en' => $l->creado_en?->toIso8601String(),
            'atendida_en' => $l->atendida_en?->toIso8601String(),
            'cocinero_nombre' => $nombreCocinero ?: 'Cocina',
        ];
    }
}
