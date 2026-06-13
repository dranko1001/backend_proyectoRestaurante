<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mesa;
use App\Models\Pedido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class AdminMesaController extends Controller
{
    private const ABIERTOS = ['PENDIENTE', 'EN_PREPARACION', 'LISTO'];

    public function index(Request $request): JsonResponse
    {
        $verEliminadas = $request->boolean('eliminadas');

        $mesas = Mesa::query()
            ->when(
                $verEliminadas,
                fn ($q) => $q->whereNotNull('eliminada_en'),
                fn ($q) => $q->whereNull('eliminada_en'),
            )
            ->orderBy('numero')
            ->get();

        $totalEliminadas = (int) Mesa::query()->whereNotNull('eliminada_en')->count();

        return response()->json([
            'data' => $mesas->map(fn (Mesa $m) => $this->serializeMesa($m)),
            'total_eliminadas' => $totalEliminadas,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $nom = $request->input('nombre');
        $request->merge([
            'nombre' => is_string($nom) && trim($nom) !== '' ? trim($nom) : null,
        ]);

        $numeroEliminada = Mesa::query()
            ->where('numero', (int) $request->input('numero'))
            ->whereNotNull('eliminada_en')
            ->exists();

        if ($numeroEliminada) {
            return response()->json([
                'message' => 'Ese número pertenece a una mesa borrada. Restáurala desde «Mesas borradas» o usa otro número.',
            ], 422);
        }

        $data = $request->validate([
            'numero' => ['required', 'integer', 'min:1', 'max:9999', Rule::unique('mesa', 'numero')],
            'nombre' => ['nullable', 'string', 'max:80', Rule::unique('mesa', 'nombre')],
            'capacidad' => ['required', 'integer', 'min:1', 'max:99'],
            'activa' => ['sometimes', 'boolean'],
        ]);

        $mesa = Mesa::create([
            'numero' => $data['numero'],
            'nombre' => $data['nombre'] ?? null,
            'capacidad' => $data['capacidad'],
            'estado' => 'LIBRE',
            'activa' => array_key_exists('activa', $data) ? (bool) $data['activa'] : true,
        ]);

        return response()->json([
            'message' => 'Mesa creada.',
            'data' => $this->serializeMesa($mesa),
        ], 201);
    }

    public function update(Request $request, Mesa $mesa): JsonResponse
    {
        $nom = $request->input('nombre');
        $request->merge([
            'nombre' => is_string($nom) && trim($nom) !== '' ? trim($nom) : null,
        ]);

        $data = $request->validate([
            'numero' => ['required', 'integer', 'min:1', 'max:9999', Rule::unique('mesa', 'numero')->ignore($mesa->idMesa, 'idMesa')],
            'nombre' => ['nullable', 'string', 'max:80', Rule::unique('mesa', 'nombre')->ignore($mesa->idMesa, 'idMesa')],
            'capacidad' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $mesa->numero = $data['numero'];
        $mesa->nombre = $data['nombre'] ?? null;
        $mesa->capacidad = $data['capacidad'];
        $mesa->save();

        return response()->json([
            'message' => 'Mesa actualizada.',
            'data' => $this->serializeMesa($mesa->refresh()),
        ]);
    }

    public function setActivo(Request $request, Mesa $mesa): JsonResponse
    {
        $data = $request->validate([
            'activa' => ['required', 'boolean'],
        ]);

        if (! $data['activa']) {
            $pedidoAbierto = Pedido::query()
                ->where('mesa_idMesa', $mesa->idMesa)
                ->whereIn('estado', self::ABIERTOS)
                ->exists();

            if ($pedidoAbierto) {
                return response()->json([
                    'message' => 'No puedes desactivar la mesa mientras tenga un pedido abierto.',
                ], 422);
            }
        }

        $mesa->activa = (bool) $data['activa'];
        $mesa->save();

        return response()->json([
            'message' => $mesa->activa ? 'Mesa activada.' : 'Mesa desactivada.',
            'data' => $this->serializeMesa($mesa),
        ]);
    }

    /**
     * Borrado suave: la mesa se oculta del panel y deja de estar disponible,
     * pero se conserva en la base de datos (junto con su historial).
     */
    public function destroy(Mesa $mesa): JsonResponse
    {
        if ($mesa->eliminada_en !== null) {
            return response()->json(['message' => 'Esta mesa ya fue eliminada.'], 422);
        }

        $pedidoAbierto = Pedido::query()
            ->where('mesa_idMesa', $mesa->idMesa)
            ->whereIn('estado', self::ABIERTOS)
            ->exists();

        if ($pedidoAbierto) {
            return response()->json([
                'message' => 'No puedes eliminar la mesa mientras tenga un pedido abierto.',
            ], 422);
        }

        $mesa->eliminada_en = Carbon::now();
        $mesa->activa = false;
        $mesa->save();

        return response()->json([
            'message' => 'Mesa eliminada. Puedes restaurarla desde «Mesas borradas».',
            'data' => $this->serializeMesa($mesa),
        ]);
    }

    /**
     * Restaura una mesa borrada (vuelve visible y activa).
     */
    public function restaurar(Mesa $mesa): JsonResponse
    {
        if ($mesa->eliminada_en === null) {
            return response()->json(['message' => 'Esta mesa no está eliminada.'], 422);
        }

        $mesa->eliminada_en = null;
        $mesa->activa = true;
        $mesa->save();

        return response()->json([
            'message' => 'Mesa restaurada.',
            'data' => $this->serializeMesa($mesa),
        ]);
    }

    /**
     * Historial de pedidos de la mesa (más recientes primero).
     */
    public function historialPedidos(Mesa $mesa): JsonResponse
    {
        $pedidos = Pedido::query()
            ->where('mesa_idMesa', $mesa->idMesa)
            ->with(['mesero:idUsuario,nombre,apellido'])
            ->orderByDesc('creado_en')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $pedidos->map(function (Pedido $p) {
                return [
                    'idPedido' => $p->idPedido,
                    'estado' => $p->estado,
                    'creado_en' => $p->creado_en?->toIso8601String(),
                    'cerrado_en' => $p->cerrado_en?->toIso8601String(),
                    'notas' => $p->notas,
                    'mesero' => $p->mesero
                        ? trim(($p->mesero->nombre ?? '').' '.($p->mesero->apellido ?? ''))
                        : null,
                ];
            }),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMesa(Mesa $mesa): array
    {
        return [
            'idMesa' => $mesa->idMesa,
            'numero' => $mesa->numero,
            'nombre' => $mesa->nombre,
            'capacidad' => $mesa->capacidad,
            'estado' => $mesa->estado,
            'activa' => (bool) $mesa->activa,
            'eliminada_en' => $mesa->eliminada_en?->toIso8601String(),
        ];
    }
}
