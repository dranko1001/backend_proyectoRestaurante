<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

class AdminTwoFactorController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = $request->user();

        $pendingSetup = $usuario->two_factor_secret !== null
            && $usuario->two_factor_confirmed_at === null;

        return response()->json([
            'data' => [
                'enabled' => $usuario->hasEnabledTwoFactorAuthentication(),
                'confirmed' => $usuario->two_factor_confirmed_at !== null,
                'pending_setup' => $pendingSetup,
                'correo' => $usuario->correo,
                'qr_svg' => $pendingSetup ? $usuario->twoFactorQrCodeSvg() : null,
                'recovery_codes' => $pendingSetup ? $usuario->recoveryCodes() : null,
            ],
        ]);
    }

    public function enable(
        Request $request,
        EnableTwoFactorAuthentication $enable,
    ): JsonResponse {
        /** @var Usuario $usuario */
        $usuario = $request->user();

        if ($usuario->hasEnabledTwoFactorAuthentication()) {
            return response()->json([
                'message' => 'La verificación en dos pasos ya está activa.',
            ], 409);
        }

        $enable($usuario);

        $usuario->refresh();

        return response()->json([
            'message' => 'Escanea el código QR con Google Authenticator, Authy u otra app TOTP.',
            'data' => [
                'qr_svg' => $usuario->twoFactorQrCodeSvg(),
                'recovery_codes' => $usuario->recoveryCodes(),
                'confirmed' => false,
            ],
        ]);
    }

    public function confirm(
        Request $request,
        ConfirmTwoFactorAuthentication $confirm,
    ): JsonResponse {
        $data = $request->validate([
            'code' => ['required', 'string'],
        ]);

        /** @var Usuario $usuario */
        $usuario = $request->user();

        try {
            $confirm($usuario, $data['code']);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages([
                'code' => ['El código no es válido. Espera al siguiente código de 6 dígitos e inténtalo de nuevo.'],
            ]);
        }

        return response()->json([
            'message' => 'Verificación en dos pasos activada correctamente.',
            'data' => [
                'enabled' => true,
                'confirmed' => true,
            ],
        ]);
    }

    public function recoveryCodes(
        Request $request,
        GenerateNewRecoveryCodes $generate,
    ): JsonResponse {
        /** @var Usuario $usuario */
        $usuario = $request->user();

        if (! $usuario->hasEnabledTwoFactorAuthentication()) {
            return response()->json(['message' => 'Primero activa la verificación en dos pasos.'], 422);
        }

        $generate($usuario);
        $usuario->refresh();

        return response()->json([
            'message' => 'Nuevos códigos de recuperación generados. Guárdalos en un lugar seguro.',
            'data' => [
                'recovery_codes' => $usuario->recoveryCodes(),
            ],
        ]);
    }

    public function disable(
        Request $request,
        DisableTwoFactorAuthentication $disable,
    ): JsonResponse {
        $data = $request->validate([
            'password' => ['required', 'string'],
        ]);

        /** @var Usuario $usuario */
        $usuario = $request->user();

        if (! Hash::check($data['password'], (string) $usuario->password)) {
            throw ValidationException::withMessages([
                'password' => ['Contraseña incorrecta.'],
            ]);
        }

        $disable($usuario);

        return response()->json([
            'message' => 'Verificación en dos pasos desactivada.',
        ]);
    }
}
