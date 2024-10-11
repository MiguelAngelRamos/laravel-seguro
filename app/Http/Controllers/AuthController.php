<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Prometheus\CollectorRegistry;
/**
 * @OA\Info(
 *     title="API REST con JWT y MFA",
 *     version="1.0.0",
 *     description="API para la autenticación de usuarios con JWT y autenticación multifactor (MFA)."
 * )
 */
class AuthController extends Controller
{
    // Método para registrar un nuevo usuario y devolver el token JWT
    /**
     * @OA\Post(
     *     path="/api/register",
     *     summary="Registrar un nuevo usuario",
     *     tags={"Autenticación"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "password", "password_confirmation"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Usuario registrado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="access_token", type="string"),
     *             @OA\Property(property="token_type", type="string", example="bearer"),
     *             @OA\Property(property="expires_in", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Error de validación")
     * )
     */
    public function register(Request $request)
    {
        // Validar los datos con reglas adicionales para la contraseña
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => [
                'required',
                'string',
                'min:8', // Mínimo 8 caracteres
                'regex:/[a-z]/', // Al menos una letra minúscula
                'regex:/[A-Z]/', // Al menos una letra mayúscula
                'regex:/[0-9]/', // Al menos un número
                'regex:/[@$!%*?&]/', // Al menos un carácter especial
                'confirmed', // Confirmación de contraseña
            ],
        ], [
            // Mensajes personalizados para las reglas de validación de la contraseña
            'password.regex' => 'La contraseña debe contener al menos una letra mayúscula, una minúscula, un número y un carácter especial (@$!%*?&).',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        // Asignar rol por defecto "User" (Evitar que el usuario manipule el rol)
        // Crear nuevo usuario
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

          // Contador de usuarios registrados
        $registry = app(CollectorRegistry::class);
        $counter = $registry->getOrRegisterCounter('app', 'user_registrations_total', 'Total de usuarios registrados');
        $counter->inc(); // Incrementar el contador en 1
        // Autenticar el usuario y generar el token JWT
        $token = JWTAuth::fromUser($user);
        // Devolver el token JWT al usuario después del registro
        return $this->respondWithToken($token);
    }


    // Método para iniciar sesión
    /**
     * @OA\Post(
     *     path="/api/login",
     *     summary="Iniciar sesión",
     *     tags={"Autenticación"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Inicio de sesión exitoso",
     *         @OA\JsonContent(
     *             @OA\Property(property="access_token", type="string"),
     *             @OA\Property(property="mfa_required", type="boolean", example=false),
     *             @OA\Property(property="token_type", type="string", example="bearer"),
     *             @OA\Property(property="expires_in", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autorizado")
     * )
     */
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (!$token = auth('api')->attempt($credentials)) {
             // Contador de inicios de sesión fallidos
            $registry = app(CollectorRegistry::class); // sirve para registrar métricas
            $failedCounter = $registry->getOrRegisterCounter('app', 'failed_logins_total', 'Total de inicios de sesión fallidos');
            $failedCounter->inc(); // Incrementar el contador en 1
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Contador de inicios de sesión exitosos
        $registry = app(CollectorRegistry::class);
        $counter = $registry->getOrRegisterCounter('app', 'successful_logins_total', 'Total de inicios de sesión exitosos');
        $counter->inc(); // Incrementar el contador en 1
        $user = auth('api')->user();

        // Si MFA ya está habilitado para este usuario, requerimos MFA
        if ($user->google2fa_secret && $user->mfa_enabled) {
            return response()->json([
                'message' => 'MFA required',
                'mfa_required' => true,
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60,
                'user' => $user,
            ]);
        }

        // Si no tiene MFA habilitado, se responde con el token JWT directamente
        return $this->respondWithToken($token);
    }
    // Método para activar MFA desde el perfil del usuario
    /**
     * @OA\Post(
     *     path="/api/enable-mfa",
     *     summary="Habilitar MFA para un usuario autenticado",
     *     tags={"Autenticación"},
     *     security={{ "bearerAuth":{} }},
     *     @OA\Response(
     *         response=200,
     *         description="MFA habilitado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="MFA enabled successfully"),
     *             @OA\Property(property="qrCodeUrl", type="string"),
     *             @OA\Property(property="mfa_enabled", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autorizado")
     * )
     */
    public function enableMFA(Request $request)
    {
        $user = auth()->user();
        $google2fa = new Google2FA();

        // Generar clave secreta para Google2FA
        $user->google2fa_secret = $google2fa->generateSecretKey();
        $user->mfa_enabled = true; // Aquí se actualiza el estado MFA a "habilitado"
        $user->save();

        // Generar código QR
        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $user->google2fa_secret
        );

        // Devolver la URL del QR al frontend para que pueda ser escaneado
        return response()->json([
            'message' => 'MFA enabled successfully',
            'qrCodeUrl' => $qrCodeUrl,
            'mfa_enabled' => true // Notificar al frontend que MFA está habilitado
        ]);
    }

    // Método para verificar MFA
    /**
     * @OA\Post(
     *     path="/api/verify-mfa",
     *     summary="Verificar el código de MFA",
     *     tags={"Autenticación"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"otp"},
     *             @OA\Property(property="otp", type="string", example="123456")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="MFA verificado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="access_token", type="string"),
     *             @OA\Property(property="token_type", type="string", example="bearer"),
     *             @OA\Property(property="expires_in", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Código MFA inválido")
     * )
     */
    public function verifyMFA(Request $request)
    {
        $request->validate(['otp' => 'required']);

        $google2fa = new Google2FA();
        $user = auth('api')->user(); // Usuario autenticado

        // Verificar el código OTP con el secreto almacenado del usuario
        $valid = $google2fa->verifyKey($user->google2fa_secret, $request->otp);

        if ($valid) {
            // Si el MFA es válido, generar un nuevo token JWT
            $token = auth('api')->refresh();
            return $this->respondWithToken($token);
        }

        return response()->json(['error' => 'Invalid MFA code'], 401);
    }

    // Método para mostrar el perfil del usuario autenticado
    /**
     * @OA\Get(
     *     path="/api/user-profile",
     *     summary="Obtener el perfil del usuario autenticado",
     *     tags={"Usuario"},
     *     security={{ "bearerAuth":{} }},
     *     @OA\Response(
     *         response=200,
     *         description="Perfil del usuario autenticado",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autorizado")
     * )
     */
    public function profile()
    {
        $user = auth('api')->user();

        return response()->json([
            'user' => $user,
        ]);
    }

    // Método para cambiar de Email
    public function changeEmail(Request $request)
    {
        $request->validate([
            'new_email' => 'required|email|unique:users,email',
            'password' => 'required|string',
        ]);

        $user = auth('api')->user();

        // Verificar la contraseña actual
        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Contraseña incorrecta'], 403);
        }

        // Cambiar el correo electrónico
        $user->email = $request->new_email;
        $user->save();

        // Invalidar el token JWT actual
        auth()->logout();

        return response()->json(['message' => 'Correo electrónico cambiado exitosamente. Por favor inicia sesión nuevamente.'], 200);
    }

    // Método para cambiar la contraseña
    public function changePassword(Request $request)
    {
        // Obtener el usuario autenticado a través del token JWT
        $user = auth()->user();

        // Validar la solicitud
        $request->validate([
            'current_password' => 'required',
            'new_password' => [
                'required',
                'string',
                'min:8',
                'regex:/[a-z]/', // Al menos una letra minúscula
                'regex:/[A-Z]/', // Al menos una letra mayúscula
                'regex:/[0-9]/', // Al menos un número
                'regex:/[@$!%*?&]/', // Al menos un carácter especial
                'confirmed', // Confirmación de la nueva contraseña
            ]
        ], [
            // Mensajes personalizados de validación
            'new_password.regex' => 'La contraseña debe contener al menos una letra mayúscula, una minúscula, un número y un carácter especial (@$!%*?&).',
            'new_password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'new_password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        // Verificar que la contraseña actual es correcta
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['error' => 'La contraseña actual no es correcta'], 400);
        }

        // Actualizar la contraseña
        $user->password = Hash::make($request->new_password);
        $user->save();

        // Forzar al usuario a volver a iniciar sesión
        auth()->logout();

        return response()->json(['message' => 'Contraseña actualizada exitosamente. Por favor, inicia sesión nuevamente.']);
    }


    // Método para responder con el token JWT
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ]);
    }
}
