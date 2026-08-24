<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => User::latest()->get(),
        ]);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'firstName' => ['sometimes', 'required', 'string', 'max:100'],
            'middleName' => ['sometimes', 'nullable', 'string', 'max:100'],
            'lastName' => ['sometimes', 'required', 'string', 'max:100'],
            'nameExtension' => ['sometimes', 'nullable', 'string', 'max:20'],
            'phoneNumber' => ['sometimes', 'required', 'string', 'regex:/^09\d{9}$/', Rule::unique('users', 'phoneNumber')->ignore($user)],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'role' => ['sometimes', Rule::in(User::ROLES)],
            'password' => ['sometimes', 'required', Password::min(8)],
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'data' => $user->fresh(),
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully.',
        ]);
    }
}
