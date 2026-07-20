<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => [
                'required',
                'string',
            ],
            'password' => [
                'required',
                'string',
            ],
        ]);

        // Find user using email or badge number.
        $user = User::where('email', $validated['login'])
            ->orWhere('badgeNumber', $validated['login'])
            ->first();

        // Check whether the account and password are correct.
        if (
            !$user ||
            !Hash::check($validated['password'], $user->password)
        ) {
            throw ValidationException::withMessages([
                'login' => [
                    'Invalid email, badge number, or password.',
                ],
            ]);
        }

        // Mobile access is available to officers and registered drivers.
        if (!in_array($user->role, [User::ROLE_OFFICER, User::ROLE_DRIVER], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Only officer and driver accounts can use the mobile app.',
            ], 403);
        }

        // Remove previous mobile tokens.
        $user->tokens()
            ->where('name', 'android-app')
            ->delete();

        // Create a new mobile API token.
        $token = $user
            ->createToken('android-app')
            ->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'fullName' => $user->fullName,
                'badgeNumber' => $user->badgeNumber,
                'phoneNumber' => $user->phoneNumber,
                'driverLicense' => $user->driverLicense,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }
}
