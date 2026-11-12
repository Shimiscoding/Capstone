<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Violation;
use App\Services\CitationWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class ViolationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->isOfficer(), 403, 'Only officers can view mobile citation history.');
        $request->validate(['page' => ['sometimes', 'required', 'integer', 'min:1']]);
        $history = Violation::query()->where('enforcer_id', $request->user()->id)->orderByDesc('id')->paginate(25);
        $history->through(fn (Violation $ticket): Violation => $ticket
            ->setAttribute('evidence_photo_count', count($ticket->evidence_photos))
            ->makeHidden(['evidence_images', 'motorist_key']));

        return response()->json([
            'success' => true,
            'data' => $history->items(),
            'meta' => ['current_page' => $history->currentPage(), 'last_page' => $history->lastPage(), 'total' => $history->total()],
        ]);
    }

    public function store(Request $request, CitationWriter $writer): JsonResponse
    {
        abort_unless($request->user()->isOfficer(), 403, 'Only officers can issue tickets.');
        // Older Android builds send the citation number in the first photo's filename.
        $firstPhoto = $request->file('evidence_images.0');
        if (! $request->has('citation_number') && $firstPhoto instanceof UploadedFile
            && preg_match('/\A(TMC-[A-F0-9]{12})-evidence-1\.jpg\z/', $firstPhoto->getClientOriginalName(), $matches)) {
            $request->merge(['citation_number' => $matches[1]]);
        }
        $idempotencyKey = $request->header('Idempotency-Key');
        $citationRules = ['required', 'string', 'regex:/\ATMC-[A-F0-9]{12}\z/'];
        if ($idempotencyKey === null) {
            $citationRules = ['sometimes', ...$citationRules, Rule::unique('Ticket_issued', 'citation_number')];
        }
        $validated = $request->validate([
            'citation_number' => $citationRules,
            'first_name' => ['nullable', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'license_type' => ['nullable', 'string', 'in:professional,non_professional,student_permit'],
            'license_expiration_date' => ['nullable', 'date_format:Y-m-d'],
            'driver_address' => ['nullable', 'string', 'max:1000'],
            'accident_involved' => ['nullable', 'boolean'],
            'driver_response' => ['nullable', 'in:admitted,under_protest'],
            'plate_number' => ['nullable', 'string', 'max:50'],
            'vehicle_type' => ['nullable', 'string', 'max:1000'],
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
            'violations.*' => ['array:violation_issued_id,violations_type_id,remarks'],
            ...$this->offenseFieldRules(),
            'fine_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'location' => ['nullable', 'string', 'max:2000'],
            'evidence_image' => $request->hasFile('evidence_image') ? ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'] : ['sometimes', 'required', 'string', 'max:11200000', 'not_regex:/^evidence-file:/'],
            'evidence_images' => ['sometimes', 'required', 'array', 'min:1', 'max:5'],
            'evidence_images.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'evidence_photo_count' => ['sometimes', 'required', 'integer', 'min:0', 'max:5'],
            'signature' => ['nullable', 'string', 'max:7000000'],
        ], [
            'citation_number.regex' => 'Use a ticket number in the format TMC- followed by 12 hexadecimal characters.',
            'license_expiration_date.date_format' => 'Enter a valid license expiration date in YYYY-MM-DD format.',
            'evidence_image.uploaded' => 'The evidence photo could not be uploaded. Check that it is no larger than 8 MB and try again.',
            'evidence_images.*.uploaded' => 'An evidence photo could not be uploaded. Check that it is no larger than 8 MB and try again.',
        ]);

        $result = $writer->record($request->user(), $validated, $idempotencyKey);

        return $this->recorded($result['ticket'], $result['created'] ? 201 : 200);
    }

    private function recorded(Violation $ticket, int $status): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Violation recorded successfully.',
            'data' => $ticket->setAttribute('evidence_photo_count', count($ticket->evidence_photos)),
        ], $status);
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
        Violation $violation,
        CitationWriter $writer,
    ): JsonResponse {
        $validated = $request->validate([
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'license_number' => ['nullable', 'string'],
            'license_type' => ['nullable', 'string', 'in:professional,non_professional,student_permit'],
            'license_expiration_date' => ['nullable', 'date_format:Y-m-d'],
            'driver_address' => ['nullable', 'string', 'max:1000'],
            'accident_involved' => ['nullable', 'boolean'],
            'driver_response' => ['nullable', 'in:admitted,under_protest'],
            'plate_number' => ['nullable', 'string', 'max:50'],
            'vehicle_type' => ['nullable', 'string', 'max:1000'],
            'or_number' => ['nullable', 'string', 'max:100'],
            'cr_number' => ['nullable', 'string', 'max:100'],
            'violation_type' => ['sometimes', 'required', 'prohibits:violations', 'string', 'max:255'],
            'violations' => ['sometimes', 'required', 'prohibits:violation_type', 'array', 'min:1', 'max:100'],
            ...$this->offenseFieldRules(),
            'fine_amount' => ['sometimes', 'required', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(['paid', 'unpaid'])],
            'location' => ['nullable', 'string', 'max:2000'],
            'evidence_image' => ['sometimes', 'required', 'prohibits:evidence_images', 'string', 'max:10000000', 'not_regex:/^evidence-file:/'],
            'evidence_images' => ['sometimes', 'required', 'array', 'min:1', 'max:5'],
            'evidence_images.*' => ['required', 'string', 'max:10000000', 'not_regex:/^evidence-file:/'],
            'signature' => ['sometimes', 'required', 'string', 'max:7000000'],
        ]);

        $violation = $writer->update($violation, $validated);

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

    private function offenseFieldRules(): array
    {
        return [
            'violations.*.violation_issued_id' => ['required_without:violations.*.violations_type_id', 'integer', Rule::exists('violation_issued', 'id')],
            'violations.*.violations_type_id' => ['nullable', 'integer', Rule::exists('violations_type', 'section_id')],
            'violations.*.remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
