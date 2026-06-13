<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Services\Auth\AdminTwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class AdminAuthController extends Controller
{
    public function login(Request $request, AdminTwoFactorService $twoFactor): JsonResponse
    {
        $data = $request->validate([
            'correo' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $correo = strtolower(trim($data['correo']));

        $usuario = Usuario::query()
            ->with('cargo')
            ->where('correo', $correo)
            ->where('activo', true)
            ->first();

        if (! $usuario || ! Hash::check($data['password'], (string) $usuario->password)) {
            throw ValidationException::withMessages([
                Fortify::username() => [__('auth.failed')],
            ]);
        }

        if ($usuario->cargo?->nombre !== 'ADMINISTRADOR') {
            return response()->json([
                'message' => 'Este login es solo para ADMINISTRADOR.',
            ], 403);
        }

        if ($usuario->hasEnabledTwoFactorAuthentication()) {
            $challengeToken = $twoFactor->createChallenge($usuario, $data['device_name'] ?? null);

            return response()->json([
                'two_factor' => true,
                'challenge_token' => $challengeToken,
                'message' => 'Ingresa el código de tu app de autenticación.',
            ]);
        }

        return $twoFactor->tokenResponse($usuario, $data['device_name'] ?? null, $request);
    }

    public function twoFactorChallenge(Request $request, AdminTwoFactorService $twoFactor): JsonResponse
    {
        $data = $request->validate([
            'challenge_token' => ['required', 'string'],
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        if (empty($data['code']) && empty($data['recovery_code'])) {
            throw ValidationException::withMessages([
                'code' => ['Ingresa el código de 6 dígitos o un código de recuperación.'],
            ]);
        }

        return $twoFactor->completeChallenge(
            $data['challenge_token'],
            $data['code'] ?? null,
            $data['recovery_code'] ?? null,
        );
    }
}
