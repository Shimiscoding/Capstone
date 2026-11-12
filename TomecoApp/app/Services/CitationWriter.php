<?php

namespace App\Services;

use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationIssued;
use App\Models\ViolationType;
use App\Support\SignatureImage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CitationWriter
{
    /** @return array{ticket: Violation, created: bool} */
    public function record(User $officer, array $validated, ?string $idempotencyKey): array
    {
        // Normalize older single-photo requests into the one stored collection.
        $photos = array_values($validated['evidence_images'] ?? (isset($validated['evidence_image']) ? [$validated['evidence_image']] : []));
        if (isset($validated['evidence_photo_count']) && (int) $validated['evidence_photo_count'] !== count($photos)) {
            throw ValidationException::withMessages(['evidence_images' => 'One or more evidence photos did not reach the server. Check the attachments and retry.']);
        }
        unset($validated['evidence_image'], $validated['evidence_images'], $validated['evidence_photo_count']);
        if (isset($validated['vehicle_type'])) {
            $validated['vehicle_type'] = Str::title($validated['vehicle_type']);
        }

        if ($idempotencyKey !== null) {
            if ($idempotencyKey !== $validated['citation_number']) {
                throw ValidationException::withMessages(['citation_number' => 'The retry key must match the citation number.']);
            }
            if (isset($validated['signature']) && SignatureImage::source($validated['signature']) === null) {
                throw ValidationException::withMessages(['signature' => 'The driver signature is invalid. Capture it again and retry.']);
            }
            $validated['submission_fingerprint'] = $this->submissionFingerprint($validated, $photos);
            if ($existing = $this->matchingSubmission($validated, $officer->id)) {
                return ['ticket' => $existing, 'created' => false];
            }
        }
        $validated['enforcer_id'] = $officer->id;
        $validated['enforcer_name'] = $officer->fullName;
        $validated['enforcer_signature'] = filled($officer->signature) ? $officer->signature : null;
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

                return $ticket->refresh()->loadMissing('issuedViolations.type', 'issuedViolations.formOption');
            });
        } catch (\Throwable $exception) {
            foreach ($stored as $reference) {
                TicketEvidence::delete($reference);
            }
            if ($exception instanceof UniqueConstraintViolationException && isset($validated['citation_number'])
                && ($existing = $this->matchingSubmission($validated, $officer->id))) {
                return ['ticket' => $existing, 'created' => false];
            }
            throw $exception;
        }

        return ['ticket' => $violation, 'created' => true];
    }

    public function update(Violation $violation, array $validated): Violation
    {
        if (isset($validated['vehicle_type'])) {
            $validated['vehicle_type'] = Str::title(trim($validated['vehicle_type']));
        }
        if (isset($validated['evidence_image'])) {
            $validated['evidence_images'] = [$validated['evidence_image']];
            unset($validated['evidence_image']);
        }

        return DB::transaction(function () use ($violation, $validated): Violation {
            $ticket = Violation::query()->lockForUpdate()->findOrFail($violation->id);
            $offenses = $validated['violations'] ?? null;
            unset($validated['violations']);
            $ticket->update($validated);
            if ($offenses !== null) {
                $this->replaceOffenses($ticket, $offenses);
            }

            return $ticket->load('issuedViolations.type', 'issuedViolations.formOption');
        });
    }

    private function matchingSubmission(array $validated, int $officerId): ?Violation
    {
        $ticket = Violation::query()->where('citation_number', $validated['citation_number'])->first();
        if ($ticket === null) {
            return null;
        }
        if ((int) $ticket->enforcer_id !== $officerId || ! isset($validated['submission_fingerprint'], $ticket->submission_fingerprint)
            || ! hash_equals($ticket->submission_fingerprint, $validated['submission_fingerprint'])) {
            throw ValidationException::withMessages(['citation_number' => 'This citation number is already in use.']);
        }

        return $ticket;
    }

    private function submissionFingerprint(array $validated, array $photos): string
    {
        $validated['evidence_hashes'] = array_map(static function (UploadedFile|string $photo): string {
            if (is_string($photo)) {
                return hash('sha256', $photo);
            }
            $hash = hash_file('sha256', $photo->getPathname());
            if ($hash === false) {
                throw ValidationException::withMessages(['evidence_images' => 'An evidence photo could not be read. Attach it again and retry.']);
            }

            return $hash;
        }, $photos);

        return hash_hmac('sha256', json_encode($validated, JSON_THROW_ON_ERROR), Crypt::getKey());
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
            $rows[] = [
                'violation_issued_id' => $option->id,
                'violations_type_id' => $type?->id,
                'description_snapshot' => $type?->description ?? ($option->label === 'Others' ? $remarks : $option->label),
                'remarks' => $remarks,
            ];
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
