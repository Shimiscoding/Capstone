<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Violation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ViolationController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Violation::latest()->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'driver_name' => ['required', 'string', 'max:255'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'plate_number' => ['required', 'string', 'max:50'],
            'violation_type' => ['required', 'string', 'max:255'],
            'fine_amount' => ['required', 'numeric', 'min:0'],
            'location' => ['nullable', 'string'],
        ]);

        $violation = Violation::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Violation recorded successfully.',
            'data' => $violation,
        ], 201);
    }

    public function show(Violation $violation): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $violation,
        ]);
    }

    public function update(
        Request $request,
        Violation $violation
    ): JsonResponse {
        $validated = $request->validate([
            'driver_name' => ['sometimes', 'required', 'string'],
            'license_number' => ['nullable', 'string'],
            'plate_number' => ['sometimes', 'required', 'string'],
            'violation_type' => ['sometimes', 'required', 'string'],
            'fine_amount' => ['sometimes', 'required', 'numeric'],
            'status' => ['sometimes', 'string'],
            'location' => ['nullable', 'string'],
        ]);

        $violation->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Violation updated successfully.',
            'data' => $violation,
        ]);
    }

    public function destroy(Violation $violation): JsonResponse
    {
        $violation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Violation deleted successfully.',
        ]);
    }
}