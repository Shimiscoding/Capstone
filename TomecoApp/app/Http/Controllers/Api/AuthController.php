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
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

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

        // Find user using username or email.
        $user = User::where('email', $validated['login'])
            ->orWhere('username', $validated['login'])
            ->first();

        // Check whether the account and password are correct.
        if (
            ! $user ||
            ! Hash::check($validated['password'], $user->password)
        ) {
            throw ValidationException::withMessages([
                'login' => [
                    'Invalid username, email, or password.',
                ],
            ]);
        }

        // Mobile access is available to enforcement officers.
        if (in_array($user->effectiveAccountStatus(), [User::STATUS_INACTIVE, User::STATUS_SUSPENDED, User::STATUS_BANNED], true)) {
            return response()->json(['success' => false, 'message' => 'This account is '.$user->effectiveAccountStatus().'. Contact an administrator for assistance.'], 403);
        }
        if ($user->role !== User::ROLE_OFFICER) {
            return response()->json([
                'success' => false,
                'message' => 'Only officer accounts can use the mobile app.',
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
            'user' => $this->mobileUser($user),
        ]);
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
        ];
    }
}
