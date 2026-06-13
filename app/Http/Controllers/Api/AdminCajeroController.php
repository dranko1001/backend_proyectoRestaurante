<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cargo;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminCajeroController extends Controller
{
    public function index(): JsonResponse
    {
        $cajeros = Usuario::query()
            ->with(['cargo:idCargo,nombre'])
            ->whereHas('cargo', fn ($q) => $q->where('nombre', 'CAJERO'))
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->orderBy('apellido')
            ->get();

        return response()->json([
            'data' => $cajeros->map(fn (Usuario $u) => $this->serializeUsuario($u)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'apellido' => ['required', 'string', 'max:120'],
            'cedula' => ['required', 'string', 'max:32', Rule::unique('usuario', 'cedula')],
            'telefono' => ['required', 'string', 'max:40'],
            'correo' => ['required', 'email', 'max:190', Rule::unique('usuario', 'correo')],
            'password' => ['required', 'string', 'min:6', 'max:120'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $cargoId = Cargo::query()
            ->where('nombre', 'CAJERO')
            ->value('idCargo');

        if (! $cargoId) {
            return response()->json([
                'message' => 'No existe el cargo CAJERO en el sistema.',
            ], 500);
        }

        $usuario = Usuario::create([
            'nombre' => $data['nombre'],
            'apellido' => $data['apellido'],
            'cedula' => $data['cedula'],
            'telefono' => $data['telefono'],
            'correo' => $data['correo'],
            'password' => Hash::make((string) $data['password']),
            'cargos_idCargo' => $cargoId,
            'activo' => array_key_exists('activo', $data) ? (bool) $data['activo'] : true,
            'creado_en' => now(),
        ]);

        $usuario->loadMissing(['cargo:idCargo,nombre']);

        return response()->json([
            'message' => 'Cajero creado.',
            'data' => $this->serializeUsuario($usuario),
        ], 201);
    }

    public function update(Request $request, Usuario $usuario): JsonResponse
    {
        $usuario->loadMissing('cargo');
        if ($usuario->cargo?->nombre !== 'CAJERO') {
            return response()->json([
                'message' => 'Este endpoint solo permite editar usuarios CAJERO.',
            ], 409);
        }

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'apellido' => ['required', 'string', 'max:120'],
            'cedula' => ['required', 'string', 'max:32', Rule::unique('usuario', 'cedula')->ignore($usuario->idUsuario, 'idUsuario')],
            'telefono' => ['required', 'string', 'max:40'],
            'correo' => ['required', 'email', 'max:190', Rule::unique('usuario', 'correo')->ignore($usuario->idUsuario, 'idUsuario')],
            'password' => ['nullable', 'string', 'min:6', 'max:120'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $usuario->nombre = $data['nombre'];
        $usuario->apellido = $data['apellido'];
        $usuario->cedula = $data['cedula'];
        $usuario->telefono = $data['telefono'];
        $usuario->correo = $data['correo'];
        if (! empty($data['password'])) {
            $usuario->password = Hash::make((string) $data['password']);
        }
        if (array_key_exists('activo', $data)) {
            $usuario->activo = (bool) $data['activo'];
        }
        $usuario->save();

        $usuario->loadMissing(['cargo:idCargo,nombre']);

        return response()->json([
            'message' => 'Cajero actualizado.',
            'data' => $this->serializeUsuario($usuario),
        ]);
    }

    public function setActivo(Request $request, Usuario $usuario): JsonResponse
    {
        $usuario->loadMissing('cargo');
        if ($usuario->cargo?->nombre !== 'CAJERO') {
            return response()->json([
                'message' => 'Este endpoint solo permite deshabilitar/habilitar usuarios CAJERO.',
            ], 409);
        }

        $data = $request->validate([
            'activo' => ['required', 'boolean'],
        ]);

        $usuario->activo = (bool) $data['activo'];
        $usuario->save();

        return response()->json([
            'message' => $usuario->activo ? 'Cajero habilitado.' : 'Cajero deshabilitado.',
            'data' => $this->serializeUsuario($usuario->fresh(['cargo:idCargo,nombre'])),
        ]);
    }

    private function serializeUsuario(Usuario $u): array
    {
        return [
            'idUsuario' => $u->idUsuario,
            'nombre' => $u->nombre,
            'apellido' => $u->apellido,
            'cedula' => $u->cedula,
            'telefono' => $u->telefono,
            'correo' => $u->correo,
            'activo' => (bool) $u->activo,
            'creado_en' => $u->creado_en?->toISOString(),
            'rol' => $u->cargo?->nombre,
        ];
    }
}
