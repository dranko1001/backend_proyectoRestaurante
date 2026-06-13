<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PedidoDetalle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPedidoCancelacionController extends Controller
{
    /**
     * Platos cancelados por cocina (ítems), con contexto del pedido y mesa.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
        ]);

        $query = PedidoDetalle::query()
            ->with([
                'producto:idProducto,nombreProducto,tipo',
                'canceladoPor:idUsuario,nombre,apellido',
                'pedido' => fn ($q) => $q->with([
                    'mesa:idMesa,numero,nombre',
                    'mesero:idUsuario,nombre,apellido',
                ]),
            ])
            ->where('estado_item', 'CANCELADO')
            ->whereNotNull('motivo_cancelacion');

        if (! empty($data['desde'])) {
            $query->whereDate('cancelado_en', '>=', $data['desde']);
        }
        if (! empty($data['hasta'])) {
            $query->whereDate('cancelado_en', '<=', $data['hasta']);
        }

        $items = $query->orderByDesc('cancelado_en')->limit(300)->get();

        return response()->json([
            'data' => $items->map(fn (PedidoDetalle $d) => $this->serializeItem($d)),
            'total' => $items->count(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeItem(PedidoDetalle $d): array
    {
        $pedido = $d->pedido;
        $cocinero = $d->canceladoPor;
        $nombreCocinero = $cocinero
            ? trim(($cocinero->nombre ?? '').' '.($cocinero->apellido ?? ''))
            : null;

        return [
            'idPedidoDetalle' => $d->idPedidoDetalle,
            'idPedido' => $d->pedido_idPedido,
            'cantidad' => $d->cantidad,
            'motivo_cancelacion' => $d->motivo_cancelacion,
            'cancelado_en' => $d->cancelado_en?->toIso8601String(),
            'cancelado_por_nombre' => $nombreCocinero ?: 'Cocina',
            'producto' => $d->producto ? [
                'nombreProducto' => $d->producto->nombreProducto,
                'tipo' => $d->producto->tipo,
            ] : null,
            'pedido' => $pedido ? [
                'estado' => $pedido->estado,
                'creado_en' => $pedido->creado_en?->toIso8601String(),
                'mesa' => $pedido->mesa ? [
                    'numero' => $pedido->mesa->numero,
                    'nombre' => $pedido->mesa->nombre,
                ] : null,
                'mesero_nombre' => $pedido->mesero
                    ? trim(($pedido->mesero->nombre ?? '').' '.($pedido->mesero->apellido ?? ''))
                    : null,
            ] : null,
        ];
    }
}
