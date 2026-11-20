<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Crea una cuenta desde la terminal. Sirve para el primer administrador (no hay
 * registro público) o para recuperar el acceso si se perdió la contraseña del dueño.
 *
 *   php artisan carpisys:crear-usuario --rol=admin
 */
class CrearUsuario extends Command
{
    protected $signature = 'carpisys:crear-usuario
                            {--nombre= : Nombre completo}
                            {--email= : Correo (se usa para iniciar sesión)}
                            {--rol=operario : admin u operario}';

    protected $description = 'Crea un usuario de CarpiSys (o restablece la contraseña si el correo ya existe)';

    public function handle(): int
    {
        $rol = $this->option('rol');
        if (! in_array($rol, User::ROLES, true)) {
            $this->error('El rol debe ser: ' . implode(' u ', User::ROLES));
            return self::FAILURE;
        }

        $email = strtolower($this->option('email') ?: $this->ask('Correo'));
        $user  = User::where('email', $email)->first();

        $nombre = $user?->name ?? ($this->option('nombre') ?: $this->ask('Nombre completo'));
        $clave  = $this->secret('Contraseña (mínimo 8 caracteres)');

        $v = Validator::make(
            ['name' => $nombre, 'email' => $email, 'password' => $clave],
            [
                'name'     => 'required|string|max:255',
                'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
                'password' => ['required', Password::min(8)],
            ]
        );
        if ($v->fails()) {
            foreach ($v->errors()->all() as $e) {
                $this->error($e);
            }
            return self::FAILURE;
        }

        $user ??= new User(['email' => $email]);
        $user->name              = $nombre;
        $user->password          = $clave;
        $user->role              = $rol;
        $user->is_active         = true;
        $user->email_verified_at = now();
        $user->save();

        $this->info("Listo: {$user->email} ({$user->role}).");
        return self::SUCCESS;
    }
}
