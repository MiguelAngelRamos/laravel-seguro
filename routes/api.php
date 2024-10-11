<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookController;

// Rutas de autenticación
Route::post('register', [AuthController::class, 'register']);
// Route::post('login', [AuthController::class, 'login']);
// Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1'); // 5 intentos en 1 minuto
Route::post('login', [AuthController::class, 'login'])->middleware('login.throttle');


// Ruta para habilitar MFA desde el perfil del usuario
Route::middleware('auth:api')->post('enable-mfa', [AuthController::class, 'enableMFA']);

// Ruta para cambio de email
Route::middleware('auth:api')->post('change-email', [AuthController::class, 'changeEmail']);

// Ruta para cambio de contraseña
Route::middleware('auth:api')->post('change-password', [AuthController::class, 'changePassword']);

// Ruta para verificar el código MFA
Route::post('verify-mfa', [AuthController::class, 'verifyMFA']);

// Rutas protegidas por autenticación JWT y MFA
Route::middleware('jwt')->group(function () {
    Route::get('user-profile', [AuthController::class, 'profile']);
});

Route::middleware(['jwt', 'role:Admin'])->group(function () {
    Route::delete('books/{id}', [BookController::class, 'destroy']);  // Eliminar libro (solo Admin)
    // Agregar aquí las rutas de actualización cuando las definas
});

Route::middleware('jwt')->group(function () {
    // CRUD de libros
    Route::post('books', [BookController::class, 'store']);      // Crear libro
    Route::get('books', [BookController::class, 'index']);       // Listar todos los libros (problema de seguridad)
    Route::get('books/{id}', [BookController::class, 'show']);   // Mostrar un libro específico por su identificador
});
