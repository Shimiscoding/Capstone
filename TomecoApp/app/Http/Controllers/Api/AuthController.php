<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function sendPasswordResetLink(Request $request): JsonResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email', 'max:255']]);

        Password::sendResetLink($credentials + ['role' => User::ROLE_OFFICER]);

        return response()->json([
            'success' => true,
            'message' => 'If this email is registered, password reset instructions will be sent.',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['success' => true, 'message' => 'Logged out successfully.']);
    }

    public function profile(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->mobileUser($request->user()),
        ]);
    }

    public function updateSignature(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'signature' => ['required', 'string', 'max:7000000'],
        ]);

        $request->user()->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Enforcer signature saved successfully.',
            'data' => $this->mobileUser($request->user()->fresh()),
        ]);
    }

    public function login(Request $request, Settings $settings): JsonResponse
    {
        $validated = $request->validate([
            'login' => [
                'required',
                'string',
                'max:255',
            ],
            'password' => [
                'required',
                'string',
            ],
        ]);

        $login = $validated['login'];
        $user = User::where('email', $login)->orWhere('username', $login)->first();
        $identity = $user ? 'user:'.$user->id : 'login:'.hash('sha256', Str::lower($login));
        $key = 'mobile-login:'.$identity.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, (int) $settings->get('security.login_attempts'))) {
            $retryAfter = RateLimiter::availableIn($key);

            return response()->json([
                'message' => "Too many login attempts. Try again in {$retryAfter} seconds.",
            ], 429, ['Retry-After' => $retryAfter]);
        }

        RateLimiter::hit($key, 60);
        $response = DB::transaction(function () use ($user, $validated): JsonResponse {
            // Serialize credential checks and token replacement with password resets and logins.
            $user = $user === null ? null : User::query()->lockForUpdate()->find($user->id);
            if (! $user || ! Hash::check($validated['password'], $user->password)) {
                throw ValidationException::withMessages(['login' => ['Invalid username, email, or password.']]);
            }
            if ($user->isAccountUnavailable()) {
                return response()->json(['success' => false, 'message' => 'This account is '.$user->effectiveAccountStatus().'. Contact an administrator for assistance.'], 403);
            }
            if (! $user->isOfficer()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only officer accounts can use the mobile app.',
                ], 403);
            }

            $user->tokens()->where('name', User::MOBILE_TOKEN_NAME)->delete();
            $token = $user->createToken(User::MOBILE_TOKEN_NAME)->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login successful.',
                'token' => $token,
                'user' => $this->mobileUser($user),
            ]);
        });
        if ($response->isSuccessful()) {
            RateLimiter::clear($key);
        }

        return $response;
    }

    private function mobileUser(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'address' => $user->address,
            'area' => $user->area,
            'supervisor_id' => $user->supervisor_id,
            'fullName' => $user->fullName,
            'firstName' => $user->firstName,
            'middleName' => $user->middleName,
            'lastName' => $user->lastName,
            'nameExtension' => $user->nameExtension,
            'phoneNumber' => $user->phoneNumber,
            'email' => $user->email,
            'role' => $user->role,
            'signature' => $user->signature,
            'profile_photo_url' => $user->profile_photo_url,
        ];
    }
}
