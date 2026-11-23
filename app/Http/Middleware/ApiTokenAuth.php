<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApiTokenAuth
{
    public function handle(Request $request, Closure $next)
    {
        // Extraer el token de la cabecera Authorization (Bearer <token>)
        $authorization = $request->header('Authorization', '');
        $token = trim((string) str_replace('Bearer ', '', $authorization));

        if ($token === '') {
            return response()->json(['message' => 'No autorizado. Token no proporcionado.'], 401);
        }

        // Buscar el usuario asignado al api_token
        $user = User::query()->where('api_token', $token)->first();

        // Si prefieres seguir usando remember_token por ahora, cambia 'api_token' por 'remember_token'
        if (! $user || ($user->api_token_expires_at && $user->api_token_expires_at->isPast())) {
            return response()->json(['message' => 'Token inválido o expirado.'], 401);
        }

        // Verifica que la cuenta del usuario no esté desactivada
        if (isset($user->is_active) && ! $user->is_active) {
            return response()->json(['message' => 'Usuario inactivo. Acceso denegado.'], 403);
        }

        // Establecer el usuario autenticado en el guard de Laravel
        Auth::setUser($user);

        // Garantiza que $request->user() funcione en todos tus controladores de API
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
