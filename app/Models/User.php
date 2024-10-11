<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject; // Cambia la importación de JWTSubject
use PragmaRX\Google2FALaravel\Support\Authenticatable as TwoFactorAuthenticatable;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google2fa_secret', // Asegúrate de que este campo sea fillable
        'role', // Asegúrate de que este campo sea fillable
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'google2fa_secret' // Es recomendable ocultar el campo secreto de Google2FA
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Implementación de métodos de JWTSubject
     */

    // Devuelve la clave primaria del usuario para usar como identificador del JWT
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    // Devuelve un array de claims personalizados que se agregarán al JWT
    public function getJWTCustomClaims(): array
    {
        return [];
    }
}
