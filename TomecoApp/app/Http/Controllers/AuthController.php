<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Settings;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password as PasswordBroker;
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

    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    public function sendPasswordResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = PasswordBroker::sendResetLink($request->only('email'));

        return $status === PasswordBroker::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->withErrors(['email' => __($status)])->onlyInput('email');
    }

    public function showResetPassword(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $attributes = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => [
                'required',
                'confirmed',
                Password::min((int) app(Settings::class)->get('security.password_min_length', 8)),
            ],
        ]);

        $status = PasswordBroker::reset(
            $attributes,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        return $status === PasswordBroker::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => __($status)])->withInput($request->only('email'));
    }

    public function login(Request $request): RedirectResponse
    {
        // Keep accepting the previous `email` field for older clients while the
        // login page now submits a single username-or-email value.
        $request->merge(['login' => $request->input('login', $request->input('email'))]);
        $attributes = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $key = Str::lower($attributes['login']).'|'.$request->ip();
        $attempts = (int) app(Settings::class)->get('security.login_attempts', 5);
        if (RateLimiter::tooManyAttempts($key, $attempts)) {
            return back()->withErrors(['login' => 'Too many login attempts. Try again in '.RateLimiter::availableIn($key).' seconds.'])->onlyInput('login');
        }

        $user = User::query()
            ->where('email', $attributes['login'])
            ->orWhere('username', $attributes['login'])
            ->first();

        if (! $user || ! Auth::attempt(['id' => $user->id, 'password' => $attributes['password']], $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);

            return back()
                ->withErrors(['login' => 'The provided username, email, or password does not match our records.'])
                ->onlyInput('login');
        }

        RateLimiter::clear($key);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function register(Request $request): RedirectResponse
    {
        $attributes = $request->validate([
            'fullName' => ['sometimes', 'string', 'max:255'],
            'firstName' => ['required_without:fullName', 'string', 'max:100'],
            'middleName' => ['nullable', 'string', 'max:100'],
            'lastName' => ['required_without:fullName', 'string', 'max:100'],
            'nameExtension' => ['nullable', 'string', 'max:20'],
            'phoneNumber' => ['required', 'string', 'regex:/^09\d{9}$/', 'unique:users,phoneNumber'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(app(Settings::class)->get('security.password_min_length', 8))],
        ]);

        $user = User::create($attributes);

        Auth::login($user);
        $request->session()->regenerate();

        EmailOtpVerificationController::sendOtp($user);

        return redirect()->route('verification.notice');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
