# Laravel API REST con Autenticación JWT y MFA

## Descripción del Proyecto

Este proyecto implementa una API REST en **Laravel** que utiliza **JWT (JSON Web Token)** para la autenticación de usuarios, con soporte para **Autenticación Multifactor (MFA)** utilizando **Google Authenticator**. Además, se conecta a una base de datos **SQL Server**.

Los usuarios pueden registrarse, iniciar sesión, activar MFA escaneando un código QR y, posteriormente, utilizar MFA al iniciar sesión.

## Requerimientos Previos

- **PHP 8.0 o superior**
- **Composer**
- **SQL Server**
- **Laravel 10**
- **Docker y Docker Compose** (Opcional para levantar el entorno completo)
- **Servidor Web (Apache, Nginx, etc.)**

## Instalaciones Necesarias

### 1. Instalar Laravel

Si no tienes Laravel instalado, puedes instalarlo globalmente utilizando Composer:

```bash
composer global require laravel/installer
```

O puedes crear un proyecto Laravel directamente:

```bash
composer create-project --prefer-dist laravel/laravel apirest
```

### 2. Instalación de Dependencias para Autenticación JWT

Para manejar la autenticación JWT en Laravel, instalamos el paquete `php-open-source-saver/jwt-auth`:

```bash
composer require php-open-source-saver/jwt-auth
```

Una vez instalado, publica la configuración del paquete:

```bash
php artisan vendor:publish --provider="PHPOpenSourceSaver\JWTAuth\Providers\LaravelServiceProvider"
```

Genera la clave secreta JWT:

```bash
php artisan jwt:secret
```

### 3. Instalación de Dependencias para MFA con Google Authenticator

Instalamos el paquete `pragmarx/google2fa-laravel` para manejar la autenticación de dos factores:

```bash
composer require pragmarx/google2fa-laravel
```

Después de instalar, publicamos la configuración del paquete:

```bash
php artisan vendor:publish --provider="PragmaRX\Google2FALaravel\ServiceProvider"
```

### 4. Configuración de la Base de Datos SQL Server

En el archivo `.env`, asegúrate de configurar la conexión a SQL Server:

```env
DB_CONNECTION=sqlsrv
DB_HOST=127.0.0.1
DB_PORT=1433
DB_DATABASE=nombre_de_tu_base_de_datos
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_contraseña
```

### 5. Migraciones y Modelos

Asegúrate de tener las migraciones correctas para manejar la tabla `users` con el campo adicional `google2fa_secret`:

```bash
php artisan make:migration add_google2fa_secret_to_users_table --table=users
```

En la migración, añade el campo `google2fa_secret`:

```php
public function up()
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('google2fa_secret')->nullable()->after('password');
    });
}
```

Luego, ejecuta las migraciones:

```bash
php artisan migrate
```

### 6. Rutas y Controladores

En el archivo `routes/api.php`, asegúrate de definir las rutas necesarias para registro, login, activación de MFA y verificación de MFA:

```php
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::middleware('auth:api')->post('enable-mfa', [AuthController::class, 'enableMFA']);
Route::post('verify-mfa', [AuthController::class, 'verifyMFA']);
```

### 7. Configuración de Middlewares y JWT en el Kernel

En `app/Http/Kernel.php`, añade el middleware JWT:

```php
protected $middlewareAliases = [
    'jwt' => \PHPOpenSourceSaver\JWTAuth\Http\Middleware\Authenticate::class,
];
```

### 8. Controlador de Autenticación

Implementa los métodos en el controlador `AuthController.php` para manejar el registro, login, habilitación de MFA y verificación de MFA. El controlador debe incluir las siguientes funciones:

- **register()**: Maneja el registro de usuarios.
- **login()**: Maneja el login y verifica si MFA está habilitado.
- **enableMFA()**: Permite a los usuarios habilitar MFA.
- **verifyMFA()**: Verifica el código OTP enviado por Google Authenticator.

### 9. Levantar el Proyecto con Docker (Opcional)

Si prefieres no instalar manualmente SQL Server y PHP en tu sistema, puedes usar **Docker Compose** para levantar todo el entorno de desarrollo, incluyendo el contenedor de **SQL Server** y **Laravel**.

Crea un archivo `docker-compose.yml` en el directorio raíz del proyecto con el siguiente contenido:

```yaml
version: '3'
services:
  laravel_app:
    image: php:8.0-fpm
    container_name: laravel_app
    working_dir: /var/www/html
    volumes:
      - ./:/var/www/html
    ports:
      - "8000:8000"
    networks:
      - laravel

  sqlserver:
    image: mcr.microsoft.com/mssql/server:2019-latest
    container_name: sqlserver
    environment:
      SA_PASSWORD: "YourStrong!Passw0rd"
      ACCEPT_EULA: "Y"
    ports:
      - "1433:1433"
    networks:
      - laravel

networks:
  laravel:
    driver: bridge
```

Luego, ejecuta el siguiente comando para levantar los contenedores:

```bash
docker-compose up -d
```

Esto creará dos servicios:

1. **laravel_app**: Contiene el entorno de PHP y Laravel.
2. **sqlserver**: Contiene la base de datos SQL Server lista para usarse.

### 10. Configurar el Servidor

Para iniciar el servidor de desarrollo, utiliza el siguiente comando dentro del contenedor de Laravel:

```bash
php artisan serve --host=0.0.0.0
```

Luego accede al proyecto en tu navegador en `http://localhost:8000`.

### 11. Acceso a la Documentación Swagger (Opcional)

Si has instalado Swagger, puedes generar la documentación y acceder a ella en:

```
http://localhost:8000/api/documentation
```

## Comandos Útiles

- **Generar clave secreta JWT**: `php artisan jwt:secret`
- **Migraciones**: `php artisan migrate`
- **Limpieza de caché de configuración**: `php artisan config:clear`
- **Limpieza de caché de rutas**: `php artisan route:clear`

## Estructura de la Base de Datos

La tabla `users` debería tener la siguiente estructura básica:

- **id**: Identificador único del usuario.
- **name**: Nombre del usuario.
- **email**: Correo electrónico único.
- **password**: Contraseña encriptada.
- **google2fa_secret**: Clave secreta para MFA (nullable).
- **remember_token**: Token para recordar sesiones.

## Endpoints

- `POST /api/register`: Registro de usuarios.
- `POST /api/login`: Inicio de sesión con JWT.
- `POST /api/enable-mfa`: Activación de MFA (requiere autenticación JWT).
- `POST /api/verify-mfa`: Verificación de MFA.

## Autor

Este proyecto ha sido desarrollado por **Miguel Angel Ramos**.

## Licencia

Este proyecto está licenciado bajo la licencia MIT.
