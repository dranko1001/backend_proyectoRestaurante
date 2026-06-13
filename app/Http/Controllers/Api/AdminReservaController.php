<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reserva;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminReservaController extends Controller
{
    private const SLOT_MINUTES = 90;

    public function index(Request $request): JsonResponse
    {
        $filtro = $request->query('filtro', 'todas');

        $query = Reserva::query()
            ->with([
                'mesa:idMesa,numero,nombre,capacidad',
                'cliente:idUsuario,nombre,apellido,correo,telefono',
            ]);

        if ($filtro === 'proximas') {
            $query
                ->whereIn('estado', ['CONFIRMADA', 'SOLICITADA'])
                ->where('fecha_hora', '>=', now());
        } elseif ($filtro === 'canceladas') {
            $query->where('estado', 'CANCELADA');
        } elseif ($filtro === 'pasadas') {
            $query->where('fecha_hora', '<', now());
        }

        $items = $query
            ->orderByDesc('fecha_hora')
            ->limit(200)
            ->get();

        $conteos = [
            'todas' => (int) Reserva::query()->count(),
            'proximas' => (int) Reserva::query()
                ->whereIn('estado', ['CONFIRMADA', 'SOLICITADA'])
                ->where('fecha_hora', '>=', now())
                ->count(),
            'canceladas' => (int) Reserva::query()->where('estado', 'CANCELADA')->count(),
            'pasadas' => (int) Reserva::query()->where('fecha_hora', '<', now())->count(),
        ];

        return response()->json([
            'data' => $items->map(fn (Reserva $r) => $this->serializeReserva($r)),
            'conteos' => $conteos,
            'filtro' => $filtro,
        ]);
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

        $fin = $fh ? $fh->copy()->addMinutes(self::SLOT_MINUTES) : null;
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
}
