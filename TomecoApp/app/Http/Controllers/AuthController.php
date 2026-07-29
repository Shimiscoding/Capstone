<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function login(Request $request): RedirectResponse
    {
        $attributes = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = Str::lower($attributes['email']).'|'.$request->ip();
        $attempts = (int) app(Settings::class)->get('security.login_attempts', 5);
        if (RateLimiter::tooManyAttempts($key, $attempts)) {
            return back()->withErrors(['email' => 'Too many login attempts. Try again in '.RateLimiter::availableIn($key).' seconds.'])->onlyInput('email');
        }

        if (! Auth::attempt(['email' => $attributes['email'], 'password' => $attributes['password']], $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);

            return back()
                ->withErrors(['email' => 'The provided administrator credentials do not match our records.'])
                ->onlyInput('email');
        }

        RateLimiter::clear($key);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function register(Request $request): RedirectResponse
    {
        $attributes = $request->validate([
            'fullName' => ['required', 'string', 'max:255'],
            'badgeNumber' => ['required', 'string', 'max:50', 'unique:users,badgeNumber'],
            'phoneNumber' => ['required', 'string', 'max:30', 'unique:users,phoneNumber'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(app(Settings::class)->get('security.password_min_length', 8))],
        ]);

        $user = User::create($attributes);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
