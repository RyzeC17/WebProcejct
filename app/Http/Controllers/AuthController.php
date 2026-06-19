<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('events.list');
        }

        return view('accounts.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $login = $request->string('username')->toString();
        $loginField = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'nome_utente';

        $credentials = [
            $loginField => $login,
            'password' => $request->string('password')->toString(),
            'attivo' => true,
        ];

        if (! Auth::attempt($credentials)) {
            return back()
                ->withErrors(['username' => 'Credenziali non valide.'])
                ->onlyInput('username');
        }

        $request->session()->regenerate();
        $request->user()->forceFill(['ultimo_accesso' => Carbon::now()])->save();

        return redirect()->intended(route('events.list'))->with('success', 'Accesso effettuato con successo.');
    }

    public function showRegister(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('events.list');
        }

        return view('accounts.register');
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        $user = User::query()->create([
            'nome' => $request->string('first_name')->trim()->toString(),
            'cognome' => $request->string('last_name')->trim()->toString(),
            'nome_utente' => $request->string('username')->trim()->toString(),
            'email' => $request->string('email')->trim()->toString(),
            'password' => Hash::make($request->string('password')->toString()),
            'attivo' => true,
            'data_iscrizione' => Carbon::now(),
        ]);

        $user->assignRole(Role::query()->firstOrCreate([
            'name' => 'user',
            'guard_name' => 'web',
        ]));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('events.list')->with('success', 'Registrazione completata con successo.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
