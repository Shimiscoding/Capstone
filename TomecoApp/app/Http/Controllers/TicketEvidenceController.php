<?php

namespace App\Http\Controllers;

use App\Models\Violation;
use App\Services\TicketEvidence;
use Illuminate\Http\Request;

class TicketEvidenceController extends Controller
{
    public function show(Request $request, Violation $violation, int $index)
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isSupervisor() || ($user->isOfficer() && $violation->enforcer_id === $user->id), 403);
        $reference = $violation->evidence_photos[$index] ?? null;
        abort_unless(is_string($reference) && str_starts_with($reference, TicketEvidence::PREFIX), 404);
        $bytes = TicketEvidence::read($reference);
        $mime = getimagesizefromstring($bytes)['mime'] ?? null;
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        return response($bytes)->header('Content-Type', $mime)->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff');
    }
}
