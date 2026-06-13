<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\MasterUser;
use App\Services\Auth\MasterTwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;

class MasterTwoFactorController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        config(['database.default' => 'master']);

        /** @var MasterUser $user */
        $user = $request->user();

        $pendingSetup = $user->two_factor_secret !== null
            && $user->two_factor_confirmed_at === null;

        return response()->json([
            'data' => [
                'enabled' => $user->hasEnabledTwoFactorAuthentication(),
                'confirmed' => $user->two_factor_confirmed_at !== null,
                'pending_setup' => $pendingSetup,
                'email' => $user->email,
                'qr_svg' => $pendingSetup ? $user->twoFactorQrCodeSvg() : null,
                'recovery_codes' => $pendingSetup ? $user->recoveryCodes() : null,
            ],
        ]);
    }

    public function enable(Request $request, EnableTwoFactorAuthentication $enable): JsonResponse
    {
        config(['database.default' => 'master']);

        /** @var MasterUser $user */
        $user = $request->user();

        if ($user->hasEnabledTwoFactorAuthentication()) {
            return response()->json([
                'message' => 'La verificación en dos pasos ya está activa.',
            ], 409);
        }

        $enable($user);
        $user->refresh();

        return response()->json([
            'message' => 'Escanea el código QR con Google Authenticator, Authy u otra app TOTP.',
            'data' => [
                'qr_svg' => $user->twoFactorQrCodeSvg(),
                'recovery_codes' => $user->recoveryCodes(),
                'confirmed' => false,
            ],
        ]);
    }

    public function confirm(Request $request, ConfirmTwoFactorAuthentication $confirm): JsonResponse
    {
        config(['database.default' => 'master']);

        $data = $request->validate([
            'code' => ['required', 'string'],
        ]);

        /** @var MasterUser $user */
        $user = $request->user();

        try {
            $confirm($user, $data['code']);
        } catch (ValidationException) {
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

    public function disable(Request $request, DisableTwoFactorAuthentication $disable): JsonResponse
    {
        config(['database.default' => 'master']);

        $data = $request->validate([
            'password' => ['required', 'string', 'min:5'],
        ]);

        /** @var MasterUser $user */
        $user = $request->user();

        if (! Hash::check($data['password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Contraseña incorrecta.'],
            ]);
        }

        $disable($user);

        return response()->json([
            'message' => 'Verificación en dos pasos desactivada.',
        ]);
    }
}
