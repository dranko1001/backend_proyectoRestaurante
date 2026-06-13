<?php

namespace App\Http\Middleware;

use App\Models\Master\MasterUser;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMaster
{
    public function handle(Request $request, Closure $next): Response
    {
        config(['database.default' => 'master']);

        $plain = $request->bearerToken();
        if (! $plain) {
            return response()->json(['message' => 'No autenticado (master).'], 401);
        }

        $accessToken = PersonalAccessToken::findToken($plain);
        $user = $accessToken?->tokenable;

        if (! $user instanceof MasterUser || ! $user->activo) {
            return response()->json(['message' => 'No autenticado (master).'], 401);
        }

        $request->setUserResolver(static fn () => $user);

        return $next($request);
    }
}
