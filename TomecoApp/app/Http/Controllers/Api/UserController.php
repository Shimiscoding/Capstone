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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'firstName' => ['required_without:fullName', 'string', 'max:100'],
            'middleName' => ['nullable', 'string', 'max:100'],
            'lastName' => ['required_without:fullName', 'string', 'max:100'],
            'nameExtension' => ['nullable', 'string', 'max:20'],
            'phoneNumber' => ['required', 'string', 'regex:/^09\d{9}$/', 'unique:users,phoneNumber'],
            'driverLicense' => ['nullable', 'string', 'max:255', 'unique:users,driverLicense'],
            'plateNumber' => ['required', 'string', 'max:50', 'unique:users,plateNumber'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
        ]);

        // Public/mobile registration is exclusively for driver accounts.
        // Never trust a role supplied by the client.
        $validated['role'] = User::ROLE_DRIVER;

        $user = User::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully.',
            'data' => $user,
        ], 201);
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
        $resultingRole = $request->input('role', $user->role);

        $validated = $request->validate([
            'firstName' => ['sometimes', 'required', 'string', 'max:100'],
            'middleName' => ['sometimes', 'nullable', 'string', 'max:100'],
            'lastName' => ['sometimes', 'required', 'string', 'max:100'],
            'nameExtension' => ['sometimes', 'nullable', 'string', 'max:20'],
            'plateNumber' => [Rule::excludeIf($resultingRole !== User::ROLE_DRIVER), Rule::requiredIf($resultingRole === User::ROLE_DRIVER && blank($user->plateNumber)), 'string', 'max:50', Rule::unique('users', 'plateNumber')->ignore($user)],
            'phoneNumber' => ['sometimes', 'required', 'string', 'regex:/^09\d{9}$/', Rule::unique('users', 'phoneNumber')->ignore($user)],
            'driverLicense' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('users', 'driverLicense')->ignore($user)],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'role' => ['sometimes', Rule::in(User::ROLES)],
            'password' => ['sometimes', 'required', Password::min(8)],
        ]);

        if ($resultingRole === User::ROLE_DRIVER) {
        } else {
            $validated['plateNumber'] = null;
        }

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
