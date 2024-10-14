<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\MetricsController; // Importar el controlador de métricas

// Ruta de métricas (no requiere autenticación)
Route::get('/metrics', [MetricsController::class, '__invoke']);

// Rutas de autenticación
Route::post('register', [AuthController::class, 'register']);
// Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1'); // 5 intentos en 1 minuto
Route::post('login', [AuthController::class, 'login'])->middleware('login.throttle');

// Ruta para habilitar MFA desde el perfil del usuario (protegida por JWT extraído de cookies)
Route::middleware(['auth:api', 'jwt.cookie'])->post('enable-mfa', [AuthController::class, 'enableMFA']);

// Ruta para cambio de email (protegida por JWT extraído de cookies)
Route::middleware(['auth:api', 'jwt.cookie'])->post('change-email', [AuthController::class, 'changeEmail']);

// Ruta para cambio de contraseña (protegida por JWT extraído de cookies)
Route::middleware(['auth:api', 'jwt.cookie'])->post('change-password', [AuthController::class, 'changePassword']);

// Ruta para verificar el código MFA (no requiere autenticación previa, ya que el usuario aún no ha verificado MFA)
Route::post('verify-mfa', [AuthController::class, 'verifyMFA']);

// Rutas protegidas por autenticación JWT extraído de cookies y MFA
Route::middleware(['jwt.cookie'])->group(function () {
    Route::get('user-profile', [AuthController::class, 'profile']);
});

// Rutas protegidas con JWT extraído de cookies y roles de Admin
Route::middleware(['jwt.cookie', 'role:Admin'])->group(function () {
    Route::delete('books/{id}', [BookController::class, 'destroy']);  // Eliminar libro (solo Admin)
    // Agregar aquí las rutas de actualización cuando las definas
});

// CRUD de libros, protegido con JWT extraído de cookies
Route::middleware('jwt.cookie')->group(function () {
    Route::post('books', [BookController::class, 'store']);      // Crear libro
    Route::get('books', [BookController::class, 'index']);       // Listar todos los libros
    Route::get('books/{id}', [BookController::class, 'show']);   // Mostrar un libro específico por su identificador
});

