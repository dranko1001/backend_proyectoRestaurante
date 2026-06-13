<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cargo;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UsuarioController extends Controller
{
    // Roles que el admin puede gestionar desde este endpoint
    private const ROLES_PERMITIDOS = ['ADMINISTRADOR', 'MESERO', 'COCINERO', 'CAJERO'];

    /**
     * GET /admin/usuarios
     * Lista todos los usuarios gestionables.
     * Acepta ?rol=MESERO para filtrar por cargo.
     */
    public function index(Request $request): JsonResponse
    {
        $rolFiltro = $request->query('rol');

        /** @var Usuario|null $authUser */
        $authUser = $request->user();

        $query = Usuario::query()
            ->with(['cargo:idCargo,nombre'])
            ->whereHas('cargo', fn($q) => $q->whereIn('nombre', self::ROLES_PERMITIDOS))
            ->when($authUser, fn ($q) => $q->where('idUsuario', '!=', $authUser->idUsuario))
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->orderBy('apellido');

        if ($rolFiltro && in_array(strtoupper($rolFiltro), self::ROLES_PERMITIDOS, true)) {
            $query->whereHas('cargo', fn($q) => $q->where('nombre', strtoupper($rolFiltro)));
        }

        $usuarios = $query->get();

        return response()->json([
            'data' => $usuarios->map(fn(Usuario $u) => $this->serialize($u)),
        ]);
    }

    /**
     * POST /admin/usuarios
     * Crea un usuario con el rol indicado en el body.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'apellido' => ['required', 'string', 'max:120'],
            'cedula' => ['required', 'string', 'max:32', Rule::unique('usuario', 'cedula')],
            'telefono' => ['required', 'string', 'max:40'],
            'correo' => ['required', 'email', 'max:190', Rule::unique('usuario', 'correo')],
            'password' => ['required', 'string', 'min:8', 'max:120'],
            'rol' => ['required', 'string', Rule::in(self::ROLES_PERMITIDOS)],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $cargo = Cargo::query()->where('nombre', strtoupper($data['rol']))->first();

        if (!$cargo) {
            return response()->json([
                'message' => "El rol '{$data['rol']}' no existe en el sistema.",
            ], 500);
        }

        $usuario = Usuario::create([
            'nombre' => $data['nombre'],
            'apellido' => $data['apellido'],
            'cedula' => $data['cedula'],
            'telefono' => $data['telefono'],
            'correo' => $data['correo'],
            'password' => Hash::make((string) $data['password']),
            'cargos_idCargo' => $cargo->idCargo,
            'activo' => array_key_exists('activo', $data) ? (bool) $data['activo'] : true,
            'creado_en' => now(),
        ]);

        $usuario->loadMissing(['cargo:idCargo,nombre']);

        return response()->json([
            'message' => 'Usuario creado.',
            'data' => $this->serialize($usuario),
        ], 201);
    }

    /**
     * PUT /admin/usuarios/{usuario}
     * Edita datos del usuario. El rol también puede cambiarse.
     */
    public function update(Request $request, Usuario $usuario): JsonResponse
    {
        $usuario->loadMissing('cargo');

        if ($this->isSelf($request, $usuario)) {
            return response()->json([
                'message' => 'No puedes editar tu propia cuenta desde esta sección.',
            ], 409);
        }

        // Solo se pueden editar usuarios gestionables
        if (!in_array($usuario->cargo?->nombre, self::ROLES_PERMITIDOS, true)) {
            return response()->json([
                'message' => 'Este usuario no puede editarse desde este endpoint.',
            ], 409);
        }

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'apellido' => ['required', 'string', 'max:120'],
            'cedula' => [
                'required',
                'string',
                'max:32',
                Rule::unique('usuario', 'cedula')->ignore($usuario->idUsuario, 'idUsuario')
            ],
            'telefono' => ['required', 'string', 'max:40'],
            'correo' => [
                'required',
                'email',
                'max:190',
                Rule::unique('usuario', 'correo')->ignore($usuario->idUsuario, 'idUsuario')
            ],
            'password' => ['nullable', 'string', 'min:8', 'max:120'],
            'rol' => ['required', 'string', Rule::in(self::ROLES_PERMITIDOS)],
            'activo' => ['sometimes', 'boolean'],
        ]);

        // Resolver el cargo nuevo si cambió
        $cargo = Cargo::query()->where('nombre', strtoupper($data['rol']))->first();

        if (!$cargo) {
            return response()->json([
                'message' => "El rol '{$data['rol']}' no existe en el sistema.",
            ], 500);
        }

        $usuario->nombre = $data['nombre'];
        $usuario->apellido = $data['apellido'];
        $usuario->cedula = $data['cedula'];
        $usuario->telefono = $data['telefono'];
        $usuario->correo = $data['correo'];
        $usuario->cargos_idCargo = $cargo->idCargo;

        if (!empty($data['password'])) {
            $usuario->password = Hash::make((string) $data['password']);
        }

        $wasActive = (bool) $usuario->activo;

        if (array_key_exists('activo', $data)) {
            $usuario->activo = (bool) $data['activo'];
        }

        $usuario->save();
        $this->revokeTokensIfDeactivated($usuario, $wasActive);
        $usuario->load(['cargo:idCargo,nombre']);

        return response()->json([
            'message' => 'Usuario actualizado.',
            'data' => $this->serialize($usuario),
        ]);
    }

    /**
     * PATCH /admin/usuarios/{usuario}/activo
     * Activa o desactiva sin eliminar.
     */
    public function setActivo(Request $request, Usuario $usuario): JsonResponse
    {
        $usuario->loadMissing('cargo');

        if ($this->isSelf($request, $usuario)) {
            return response()->json([
                'message' => 'No puedes deshabilitar tu propia cuenta.',
            ], 409);
        }

        if (!in_array($usuario->cargo?->nombre, self::ROLES_PERMITIDOS, true)) {
            return response()->json([
                'message' => 'Este usuario no puede modificarse desde este endpoint.',
            ], 409);
        }

        $data = $request->validate([
            'activo' => ['required', 'boolean'],
        ]);

        $wasActive = (bool) $usuario->activo;
        $usuario->activo = (bool) $data['activo'];
        $usuario->save();
        $this->revokeTokensIfDeactivated($usuario, $wasActive);

        return response()->json([
            'message' => $usuario->activo ? 'Usuario habilitado.' : 'Usuario deshabilitado.',
            'data' => $this->serialize($usuario->fresh(['cargo:idCargo,nombre'])),
        ]);
    }

    // -------------------------------------------------------------------------

    private function revokeTokensIfDeactivated(Usuario $usuario, bool $wasActive): void
    {
        if ($wasActive && ! $usuario->activo) {
            $usuario->tokens()->delete();
        }
    }

    private function isSelf(Request $request, Usuario $usuario): bool
    {
        /** @var Usuario|null $authUser */
        $authUser = $request->user();

        return $authUser && (int) $authUser->idUsuario === (int) $usuario->idUsuario;
    }

    private function serialize(Usuario $u): array
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