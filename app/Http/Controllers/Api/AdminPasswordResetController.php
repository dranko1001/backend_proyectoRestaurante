<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Services\Auth\AdminPasswordResetMailer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class AdminPasswordResetController extends Controller
{
    public function sendResetLink(Request $request, AdminPasswordResetMailer $mailer): JsonResponse
    {
        $data = $request->validate([
            'correo' => ['required', 'email', 'max:190'],
        ]);

        $correo = strtolower(trim($data['correo']));
        $result = $mailer->sendForAdmin($correo);

        if ($result['status'] === Password::RESET_THROTTLED) {
            return response()->json([
                'message' => 'Ya enviamos un enlace hace poco. Espera un minuto y vuelve a intentarlo, o revisa tu bandeja de entrada y spam.',
                'email_sent' => false,
            ], 429);
        }

        $genericMessage = 'Si el correo pertenece a un administrador activo, recibirás un enlace para restablecer la contraseña.';

        if (! $result['is_admin']) {
            return response()->json(['message' => $genericMessage, 'email_sent' => false]);
        }

        if ($result['email_sent']) {
            return response()->json([
                'message' => 'Revisa tu correo (y la carpeta de spam). Te enviamos un enlace para restablecer la contraseña.',
                'email_sent' => true,
            ]);
        }

        $payload = [
            'message' => $result['error']
                ? $result['error'].' '.$genericMessage
                : $genericMessage,
            'email_sent' => false,
            'email_error' => $result['error'],
        ];

        if ($result['reset_url'] && app()->environment('local')) {
            $payload['reset_url'] = $result['reset_url'];
            $payload['message'] = 'No se pudo enviar el correo. Copia el enlace de recuperación de abajo.';
        }

        return response()->json($payload);
    }

    public function resetPassword(Request $request, ResetsUserPasswords $resetter): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'correo' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $correo = strtolower(trim((string) $request->input('correo')));

        $status = Password::broker('usuarios')->reset(
            [
                'correo' => $correo,
                'password' => $request->input('password'),
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $request->input('token'),
            ],
            function (Usuario $user) use ($request, $resetter) {
                $user->loadMissing('cargo');

                if ($user->cargo?->nombre !== 'ADMINISTRADOR') {
                    throw ValidationException::withMessages([
                        'correo' => ['Este enlace no es válido para restablecer la contraseña.'],
                    ]);
                }

                $resetter->reset($user, $request->all());
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Contraseña actualizada. Ya puedes iniciar sesión como administrador.',
            ]);
        }

        throw ValidationException::withMessages([
            'correo' => [__($status)],
        ]);
    }
}
