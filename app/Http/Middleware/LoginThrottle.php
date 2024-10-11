<?php

namespace App\Http\Middleware;

use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LoginThrottle
{
    protected $limiter;

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    public function handle(Request $request, \Closure $next)
    {
        $key = $this->throttleKey($request);

        if ($this->limiter->tooManyAttempts($key, 5)) {
            $seconds = $this->limiter->availableIn($key);
            return response()->json([
                'message' => 'Cuenta bloqueada. Intenta de nuevo en ' . ceil($seconds / 60) . ' minutos.',
            ], 429);
        }

        $this->limiter->hit($key, 300); // Bloquear durante 5 minutos si hay demasiados intentos fallidos

        return $next($request);
    }

    protected function throttleKey(Request $request)
    {
        return Str::lower($request->input('email')).'|'.$request->ip();
    }
}

/*
 * Explicación por partes:
Constructor:

Se usa para inyectar el servicio de RateLimiter al middleware, el cual nos permitirá limitar la cantidad de intentos fallidos de login.
handle():

Este método es el núcleo del middleware. En cada solicitud que pase por él, comprueba si el usuario ha hecho demasiados intentos de login fallidos.
Usa el método tooManyAttempts() para verificar si se han superado los 5 intentos permitidos.
Si el usuario ha superado los intentos, se devuelve un mensaje de error con el tiempo restante hasta que pueda intentarlo nuevamente.
Si el usuario aún no ha superado los intentos, se incrementa el contador de intentos fallidos con hit() y se continúa con la solicitud.
throttleKey():

Genera una clave única para cada usuario en función de su email y dirección IP.
Esto asegura que los intentos de login estén vinculados tanto a la cuenta del usuario como a su ubicación.
Este código se asegura de que, tras 5 intentos de login fallidos, el usuario no pueda intentar iniciar sesión nuevamente durante 5 minutos.
 * */
