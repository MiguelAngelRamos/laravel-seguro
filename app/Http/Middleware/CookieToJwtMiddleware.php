<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CookieToJwtMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Obtener el token JWT de la cookie
        $token = $request->cookie('token');

        if ($token) {
            // Establecer el token en el encabezado de la solicitud como "Authorization: Bearer {token}"
            $request->headers->set('Authorization', 'Bearer ' . $token);
        }

        return $next($request);
    }
}
