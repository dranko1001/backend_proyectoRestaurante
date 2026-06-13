<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        return $this->issueToken($request);
    }

    public function loginCliente(Request $request)
    {
        return $this->issueToken($request, requiredRole: 'CLIENTE');
    }

    public function me(Request $request)
    {
        /** @var Usuario $usuario */
        $usuario = $request->user();
        $usuario->loadMissing('cargo');

        return response()->json([
            'user' => [
                'idUsuario' => $usuario->idUsuario,
                'nombre' => $usuario->nombre,
                'apellido' => $usuario->apellido,
                'correo' => $usuario->correo,
                'cargos_idCargo' => $usuario->cargos_idCargo,
                'rol' => $usuario->cargo?->nombre,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Sesión cerrada.',
        ]);
    }

    private function issueToken(Request $request, ?string $requiredRole = null)
    {
        $data = $request->validate([
            'correo' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $usuario = Usuario::query()
            ->with('cargo')
            ->where('correo', $data['correo'])
            ->where('activo', true)
            ->first();

        if (! $usuario || ! Hash::check($data['password'], (string) $usuario->password)) {
            throw ValidationException::withMessages([
                'correo' => ['Credenciales inválidas.'],
            ]);
        }

        if ($requiredRole !== null && ($usuario->cargo?->nombre !== $requiredRole)) {
            return response()->json([
                'message' => "Este login es solo para {$requiredRole}.",
            ], 403);
        }

        $deviceName = $data['device_name'] ?? ($request->userAgent() ?: 'web');
        $token = $usuario->createToken($deviceName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'idUsuario' => $usuario->idUsuario,
                'nombre' => $usuario->nombre,
                'apellido' => $usuario->apellido,
                'correo' => $usuario->correo,
                'cargos_idCargo' => $usuario->cargos_idCargo,
                'rol' => $usuario->cargo?->nombre,
            ],
        ]);
    }
}

