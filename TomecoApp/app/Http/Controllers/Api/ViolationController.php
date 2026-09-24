<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Violation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'license_type' => ['nullable', 'string', 'in:professional,non_professional,student_permit'],
            'plate_number' => ['required', 'string', 'max:50'],
            'vehicle_type' => ['required', 'string', 'max:50'],
            'or_number' => ['nullable', 'string', 'max:100'],
            'cr_number' => ['nullable', 'string', 'max:100'],
            'violation_type' => ['required', 'string', 'max:255'],
            'fine_amount' => ['required', 'numeric', 'min:0'],
            'location' => ['nullable', 'string'],
            'evidence_image' => ['required', 'string', 'max:10000000'],
            'signature' => ['required', 'string', 'max:7000000'],
        ]);

        if (blank($request->user()->signature)) {
            throw ValidationException::withMessages([
                'enforcer_signature' => 'Save your enforcer signature in Settings before issuing a ticket.',
            ]);
        }

        $validated['enforcer_id'] = $request->user()->id;
        $validated['enforcer_name'] = $request->user()->fullName;
        $validated['enforcer_signature'] = $request->user()->signature;
        $validated['vehicle_type'] = Str::title(trim($validated['vehicle_type']));
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
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'license_number' => ['nullable', 'string'],
            'license_type' => ['nullable', 'string', 'in:professional,non_professional,student_permit'],
            'plate_number' => ['sometimes', 'required', 'string'],
            'vehicle_type' => ['sometimes', 'required', 'string', 'max:50'],
            'or_number' => ['nullable', 'string', 'max:100'],
            'cr_number' => ['nullable', 'string', 'max:100'],
            'violation_type' => ['sometimes', 'required', 'string'],
            'fine_amount' => ['sometimes', 'required', 'numeric'],
            'status' => ['sometimes', 'string'],
            'location' => ['nullable', 'string'],
            'evidence_image' => ['sometimes', 'required', 'string', 'max:10000000'],
            'signature' => ['sometimes', 'required', 'string', 'max:7000000'],
        ]);

        if (array_key_exists('vehicle_type', $validated)) {
            $validated['vehicle_type'] = Str::title(trim($validated['vehicle_type']));
        }
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
