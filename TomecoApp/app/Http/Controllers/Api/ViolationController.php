<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Violation;
use App\Models\ViolationIssued;
use App\Models\ViolationType;
use App\Services\TicketEvidence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
        abort_unless($request->user()->isOfficer(), 403, 'Only officers can issue tickets.');
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'license_type' => ['nullable', 'string', 'in:professional,non_professional,student_permit'],
            'plate_number' => ['required', 'string', 'max:50'],
            'vehicle_type' => ['required', 'string', 'max:1000'],
            'registration_plate_number' => ['nullable', 'string', 'max:100'],
            'vehicle_make' => ['nullable', 'string', 'max:255'],
            'vehicle_series' => ['nullable', 'string', 'max:255'],
            'vehicle_body_type' => ['nullable', 'string', 'max:255'],
            'vehicle_year_model' => ['nullable', 'string', 'max:100'],
            'registered_owner_name' => ['nullable', 'string', 'max:500'],
            'registered_owner_address' => ['nullable', 'string', 'max:2000'],
            'or_number' => ['nullable', 'string', 'max:100'],
            'cr_number' => ['nullable', 'string', 'max:100'],
            'violation_type' => ['required_without:violations', 'prohibits:violations', 'string', 'max:5000'],
            'violations' => ['required_without:violation_type', 'prohibits:violation_type', 'array', 'min:1', 'max:100'],
            'violations.*.violation_issued_id' => ['required_without:violations.*.violations_type_id', 'integer', Rule::exists('violation_issued', 'id')],
            'violations.*.violations_type_id' => ['nullable', 'integer', Rule::exists('violations_type', 'section_id')],
            'violations.*.remarks' => ['nullable', 'string', 'max:2000'],
            'fine_amount' => ['required', 'numeric', 'min:0'],
            'location' => ['nullable', 'string'],
            'evidence_image' => array_merge(['required_without:evidence_images'], $request->hasFile('evidence_image') ? ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'] : ['string', 'max:11200000', 'not_regex:/^evidence-file:/']),
            'evidence_images' => ['sometimes', 'required', 'array', 'min:1', 'max:5'],
            'evidence_images.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'signature' => ['required', 'string', 'max:7000000'],
        ]);

        // Normalize older single-photo requests into the one stored collection.
        $photos = isset($validated['evidence_images']) ? $validated['evidence_images'] : [$validated['evidence_image']];
        unset($validated['evidence_image']);

        if (blank($request->user()->signature)) {
            throw ValidationException::withMessages([
                'enforcer_signature' => 'Save your enforcer signature in Settings before issuing a ticket.',
            ]);
        }

        $validated['enforcer_id'] = $request->user()->id;
        $validated['enforcer_name'] = $request->user()->fullName;
        $validated['enforcer_signature'] = $request->user()->signature;
        $validated['vehicle_type'] = Str::title(trim($validated['vehicle_type']));
        $stored = [];
        try {
            foreach ($photos as $photo) {
                $stored[] = $photo instanceof UploadedFile ? TicketEvidence::store($photo) : $photo;
            }
            $validated['evidence_images'] = $stored;
            $violation = DB::transaction(function () use ($validated): Violation {
                $offenses = $validated['violations'] ?? null;
                unset($validated['violations']);
                $ticket = Violation::create($validated);
                if ($offenses !== null) {
                    $this->replaceOffenses($ticket, $offenses);
                }

                return $ticket->load('issuedViolations.type', 'issuedViolations.formOption');
            });
        } catch (\Throwable $exception) {
            foreach ($stored as $reference) {
                TicketEvidence::delete($reference);
            }
            throw $exception;
        }

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
            'violation_type' => ['sometimes', 'required', 'prohibits:violations', 'string', 'max:255'],
            'violations' => ['sometimes', 'required', 'prohibits:violation_type', 'array', 'min:1', 'max:100'],
            'violations.*.violation_issued_id' => ['required_without:violations.*.violations_type_id', 'integer', Rule::exists('violation_issued', 'id')],
            'violations.*.violations_type_id' => ['nullable', 'integer', Rule::exists('violations_type', 'section_id')],
            'violations.*.remarks' => ['nullable', 'string', 'max:2000'],
            'fine_amount' => ['sometimes', 'required', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(['paid', 'unpaid'])],
            'location' => ['nullable', 'string'],
            'evidence_image' => ['sometimes', 'required', 'prohibits:evidence_images', 'string', 'max:10000000', 'not_regex:/^evidence-file:/'],
            'evidence_images' => ['sometimes', 'required', 'array', 'min:1', 'max:5'],
            'evidence_images.*' => ['required', 'string', 'max:10000000', 'not_regex:/^evidence-file:/'],
            'signature' => ['sometimes', 'required', 'string', 'max:7000000'],
        ]);

        if (array_key_exists('vehicle_type', $validated)) {
            $validated['vehicle_type'] = Str::title(trim($validated['vehicle_type']));
        }
        if (isset($validated['evidence_image'])) {
            $validated['evidence_images'] = [$validated['evidence_image']];
            unset($validated['evidence_image']);
        }
        $violation = DB::transaction(function () use ($violation, $validated): Violation {
            $ticket = Violation::query()->lockForUpdate()->findOrFail($violation->id);
            $offenses = $validated['violations'] ?? null;
            unset($validated['violations']);
            $ticket->update($validated);
            if ($offenses !== null) {
                $this->replaceOffenses($ticket, $offenses);
            }

            return $ticket->load('issuedViolations.type', 'issuedViolations.formOption');
        });

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

    private function replaceOffenses(Violation $ticket, array $offenses): void
    {
        $types = ViolationType::query()->whereIn('section_id', array_column($offenses, 'violations_type_id'))
            ->lockForUpdate()->get()->keyBy('section_id');
        $options = ViolationIssued::query()->lockForUpdate()->get()->keyBy('id');
        $rows = [];
        $seen = [];
        foreach ($offenses as $index => $offense) {
            $typeId = $offense['violations_type_id'] ?? null;
            $type = $typeId !== null ? $types->get($typeId) : null;
            if ($typeId !== null && ! $type) {
                throw ValidationException::withMessages(['violations' => 'A selected section is no longer available.']);
            }
            $optionId = $offense['violation_issued_id'] ?? null;
            $option = $optionId !== null ? $options->get($optionId)
                : $options->get($type->violation_issued_id);
            $option ??= $optionId === null ? $options->firstWhere('label', 'Others') : null;
            if (! $option) {
                throw ValidationException::withMessages(['violations' => 'A selected form option is no longer available.']);
            }
            $key = $option->id.':'.($type?->id ?? 0);
            if ($type && (int) $type->violation_issued_id !== (int) $option->id) {
                throw ValidationException::withMessages(["violations.$index" => 'Select a section related to this form option.']);
            }
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(["violations.$index" => 'This form option and section are already selected.']);
            }
            $seen[$key] = true;
            $remarks = $offense['remarks'] ?? null;
            if (! $type && $option->label === 'Others' && blank($remarks)) {
                throw ValidationException::withMessages(["violations.$index.remarks" => 'Describe the offense when selecting Others without a section.']);
            }
            $rows[] = ['violation_issued_id' => $option->id, 'violations_type_id' => $type?->id,
                'description_snapshot' => $type?->description ?? ($option->label === 'Others' ? $remarks : $option->label),
                'remarks' => $remarks];
        }
        // Model deletion events preserve the offense audit trail.
        foreach ($ticket->issuedViolations as $existing) {
            $existing->delete();
        }
        foreach ($rows as $row) {
            $ticket->issuedViolations()->create($row);
        }
        $ticket->unsetRelation('issuedViolations');
    }
}
