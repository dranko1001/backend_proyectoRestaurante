<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mesa;
use App\Models\Reserva;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ClienteReservaController extends Controller
{
    /** Inicio de franjas (duración 90 min). Última franja: 19:00–20:30. */
    private const SLOT_STARTS = ['10:00', '11:30', '13:00', '14:30', '16:00', '17:30', '19:00'];

    private const SLOT_MINUTES = 90;

    private const MAX_DIAS_ANTICIPACION = 10;

    public function index(Request $request): JsonResponse
    {
        $clienteId = (int) $request->user()->getAuthIdentifier();

        $activa = Reserva::query()
            ->with(['mesa:idMesa,numero,nombre,capacidad'])
            ->where('cliente_idUsuario', $clienteId)
            ->whereIn('estado', ['CONFIRMADA', 'SOLICITADA'])
            ->where('fecha_hora', '>=', now())
            ->orderBy('fecha_hora')
            ->first();

        $ultima = Reserva::query()
            ->with(['mesa:idMesa,numero,nombre,capacidad'])
            ->where('cliente_idUsuario', $clienteId)
            ->orderByDesc('creado_en')
            ->orderByDesc('fecha_hora')
            ->first();

        if ($activa && $ultima && $ultima->idReserva === $activa->idReserva) {
            $ultima = null;
        }

        return response()->json([
            'reserva_activa' => $activa ? $this->serializeReserva($activa) : null,
            'ultima_reserva' => $ultima ? $this->serializeReserva($ultima) : null,
            'puede_reservar' => $activa === null,
        ]);
    }

    /**
     * Disponibilidad por franja para un día (máximo = mesas activas en el local).
     */
    public function disponibilidad(Request $request): JsonResponse
    {
        $maxFecha = now()->startOfDay()->addDays(self::MAX_DIAS_ANTICIPACION)->format('Y-m-d');

        $data = $request->validate([
            'fecha' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.$maxFecha],
        ]);

        $capacidad = $this->countMesasActivas();
        $franjas = [];

        foreach (self::SLOT_STARTS as $hora) {
            $inicio = Carbon::parse($data['fecha'].' '.$hora.':00');
            $ocupadas = $this->countReservasEnFranja($inicio);
            $pasada = $inicio->isPast();
            $disponible = ! $pasada && $capacidad > 0 && $ocupadas < $capacidad;

            $franjas[] = [
                'hora' => $hora,
                'ocupadas' => $ocupadas,
                'capacidad' => $capacidad,
                'restantes' => max(0, $capacidad - $ocupadas),
                'disponible' => $disponible,
            ];
        }

        return response()->json([
            'fecha' => $data['fecha'],
            'mesas_activas' => $capacidad,
            'franjas' => $franjas,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $maxFecha = now()->startOfDay()->addDays(self::MAX_DIAS_ANTICIPACION)->format('Y-m-d');

        $data = $request->validate([
            'fecha' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.$maxFecha],
            'hora' => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'mesa_idMesa' => ['nullable', 'integer', 'exists:mesa,idMesa'],
            'num_personas' => ['required', 'integer', 'min:1', 'max:40'],
            'notas' => ['nullable', 'string', 'max:500'],
        ]);

        if (! in_array($data['hora'], self::SLOT_STARTS, true)) {
            return response()->json([
                'message' => 'El horario elegido no está disponible. Elige una franja de 1 h 30 min entre 10:00 a. m. y 8:30 p. m.',
            ], 422);
        }

        $clienteId = (int) $request->user()->getAuthIdentifier();

        $yaTieneActiva = Reserva::query()
            ->where('cliente_idUsuario', $clienteId)
            ->whereIn('estado', ['CONFIRMADA', 'SOLICITADA'])
            ->where('fecha_hora', '>=', now())
            ->exists();

        if ($yaTieneActiva) {
            return response()->json([
                'message' => 'Ya tienes una reserva activa. Solo puedes tener una a la vez.',
            ], 422);
        }

        $fechaHora = Carbon::parse($data['fecha'].' '.$data['hora'].':00');
        if ($fechaHora->isPast()) {
            return response()->json([
                'message' => 'La fecha y hora deben ser futuras.',
            ], 422);
        }

        $capacidad = $this->countMesasActivas();
        if ($capacidad < 1) {
            return response()->json([
                'message' => 'No hay mesas disponibles para reservas en este momento.',
            ], 422);
        }

        $ocupadas = $this->countReservasEnFranja($fechaHora);
        if ($ocupadas >= $capacidad) {
            return response()->json([
                'message' => 'No hay disponibilidad en esa franja horaria. Elige otro horario.',
            ], 422);
        }

        if (! empty($data['mesa_idMesa'])) {
            $mesa = Mesa::query()
                ->where('idMesa', $data['mesa_idMesa'])
                ->where('activa', true)
                ->first();

            if (! $mesa) {
                return response()->json(['message' => 'La mesa no está disponible.'], 422);
            }

            if ((int) $data['num_personas'] > (int) $mesa->capacidad) {
                return response()->json([
                    'message' => 'El número de personas supera la capacidad de esa mesa.',
                ], 422);
            }
        }

        $reserva = Reserva::create([
            'cliente_idUsuario' => $request->user()->getAuthIdentifier(),
            'mesa_idMesa' => $data['mesa_idMesa'] ?? null,
            'fecha_hora' => $fechaHora,
            'num_personas' => $data['num_personas'],
            'estado' => 'CONFIRMADA',
            'notas' => $data['notas'] ?: null,
            'creado_en' => now(),
        ]);

        $reserva->load('mesa:idMesa,numero,nombre,capacidad');

        return response()->json([
            'message' => 'Tu reserva quedó registrada. Te esperamos en la fecha y horario elegidos.',
            'data' => $this->serializeReserva($reserva),
            'puede_reservar' => false,
        ], 201);
    }

    public function cancelar(Request $request, Reserva $reserva): JsonResponse
    {
        Gate::forUser($request->user())->authorize('cancelar', $reserva);

        if ($reserva->estado === 'CANCELADA') {
            return response()->json(['message' => 'Esta reserva ya está cancelada.'], 422);
        }

        if (! in_array($reserva->estado, ['CONFIRMADA', 'SOLICITADA'], true)) {
            return response()->json(['message' => 'Esta reserva ya no puede cancelarse.'], 422);
        }

        $data = $request->validate([
            'motivo' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $reserva->estado = 'CANCELADA';
        $reserva->motivo_cancelacion = trim($data['motivo']);
        $reserva->save();

        $reserva->load('mesa:idMesa,numero,nombre,capacidad');

        return response()->json([
            'message' => 'Tu reserva fue cancelada correctamente.',
            'data' => $this->serializeReserva($reserva),
            'puede_reservar' => true,
        ]);
    }

    private function countMesasActivas(): int
    {
        return (int) Mesa::query()->where('activa', true)->count();
    }

    /**
     * Reservas activas que solapan la franja de 90 min que inicia en $slotInicio.
     */
    private function countReservasEnFranja(Carbon $slotInicio): int
    {
        $slotFin = $slotInicio->copy()->addMinutes(self::SLOT_MINUTES);

        return (int) Reserva::query()
            ->whereIn('estado', ['SOLICITADA', 'CONFIRMADA'])
            ->whereDate('fecha_hora', $slotInicio->toDateString())
            ->where('fecha_hora', '<', $slotFin)
            ->whereRaw('DATE_ADD(fecha_hora, INTERVAL ? MINUTE) > ?', [
                self::SLOT_MINUTES,
                $slotInicio->format('Y-m-d H:i:s'),
            ])
            ->count();
    }

    private function serializeReserva(Reserva $r): array
    {
        /** @var \Carbon\Carbon|null $fh */
        $fh = $r->fecha_hora;

        /** @var \Carbon\Carbon|null $creado */
        $creado = $r->creado_en;

        return [
            'idReserva' => $r->idReserva,
            'fecha_hora' => $fh ? $fh->timezone(config('app.timezone'))->format(\DateTime::ATOM) : null,
            'num_personas' => $r->num_personas,
            'estado' => $r->estado,
            'notas' => $r->notas,
            'motivo_cancelacion' => $r->motivo_cancelacion,
            'creado_en' => $creado ? $creado->timezone(config('app.timezone'))->format(\DateTime::ATOM) : null,
            'mesa' => $r->mesa ? [
                'idMesa' => $r->mesa->idMesa,
                'numero' => $r->mesa->numero,
                'nombre' => $r->mesa->nombre,
                'capacidad' => $r->mesa->capacidad,
            ] : null,
        ];
    }
}
