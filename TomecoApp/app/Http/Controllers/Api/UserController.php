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
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isSupervisor(), 403);

        return response()->json([
            'success' => true,
            'data' => User::latest()->when($request->user()->isSupervisor(), fn ($query) => $query->where('supervisor_id', $request->user()->id)->where('role', User::ROLE_OFFICER))->get(),
        ]);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->isAdmin() || $request->user()->is($user)
            || ($request->user()->isSupervisor() && $user->isOfficer() && $user->supervisor_id === $request->user()->id), 403);

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        if ($request->has('savedSignature')) {
            $request->merge(['signature' => $request->input('savedSignature')]);
        }
        $validated = $request->validate([
            'firstName' => ['sometimes', 'required', 'string', 'max:100'],
            'middleName' => ['sometimes', 'nullable', 'string', 'max:100'],
            'lastName' => ['sometimes', 'required', 'string', 'max:100'],
            'nameExtension' => ['sometimes', 'nullable', 'string', 'max:20'],
            'phoneNumber' => ['sometimes', 'required', 'string', 'regex:/^09\d{9}$/', Rule::unique('users', 'phoneNumber')->ignore($user)],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'role' => ['sometimes', Rule::in(User::ROLES)],
            'username' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('users')->ignore($user)],
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'password' => ['sometimes', 'required', Password::min(8)],
            'signature' => ['sometimes', 'nullable', 'string', 'max:7000000'],
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'data' => $user->fresh(),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_if($request->user()->is($user), 422, 'You cannot delete your own account.');
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully.',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $validated = $request->validate([
            'firstName' => ['required', 'string', 'max:100'],
            'middleName' => ['nullable', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'nameExtension' => ['nullable', 'string', 'max:20'],
            'username' => ['required', 'string', 'max:100', Rule::unique('users')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')],
            'password' => ['required', Password::min(8)],
            'phoneNumber' => ['required', 'string', 'regex:/^09\d{9}$/', Rule::unique('users')],
            'role' => ['required', Rule::in(User::ROLES)],
            'address' => ['nullable', 'string', 'max:1000'],
            'supervisor_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', User::ROLE_SUPERVISOR)],
        ]);
        abort_if(! empty($validated['supervisor_id']) && $validated['role'] !== User::ROLE_OFFICER, 422, 'Only officers can be assigned to a supervisor.');
        $user = User::create($validated);

        return response()->json(['success' => true, 'message' => 'User created successfully.', 'data' => $user], 201);
    }
}
