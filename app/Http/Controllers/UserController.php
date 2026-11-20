<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestión de usuarios. Solo el administrador llega aquí (ver routes/web.php).
 * No hay registro público: las cuentas las crea el administrador.
 */
class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Users/Index', [
            'users' => User::orderBy('name')->get(['id', 'name', 'email', 'role', 'is_active']),
            'roles' => User::ROLES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|lowercase|email|max:255|unique:users,email',
            'password' => ['required', Password::min(8)],
            'role'     => ['required', Rule::in(User::ROLES)],
        ]);

        $user = new User([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => $data['password'], // el cast "hashed" lo cifra
        ]);
        $user->role      = $data['role'];
        $user->is_active = true;
        $user->save();

        return redirect()->route('users.index')->with('success', 'Usuario creado.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name'      => 'sometimes|required|string|max:255',
            'role'      => ['sometimes', 'required', Rule::in(User::ROLES)],
            'is_active' => 'sometimes|boolean',
            'password'  => ['nullable', Password::min(8)],
        ]);

        // Evita quedarse sin administrador o sin acceso por accidente.
        if ($user->is($request->user())) {
            if (isset($data['role']) && $data['role'] !== User::ROLE_ADMIN) {
                return back()->withErrors(['role' => 'No puedes quitarte a ti mismo el rol de administrador.']);
            }
            if (array_key_exists('is_active', $data) && ! $data['is_active']) {
                return back()->withErrors(['is_active' => 'No puedes desactivar tu propia cuenta.']);
            }
        }

        if (isset($data['name']))                       $user->name      = $data['name'];
        if (isset($data['role']))                       $user->role      = $data['role'];
        if (array_key_exists('is_active', $data))       $user->is_active = $data['is_active'];
        if (! empty($data['password']))                 $user->password  = $data['password'];
        $user->save();

        return redirect()->route('users.index')->with('success', 'Usuario actualizado.');
    }
}
