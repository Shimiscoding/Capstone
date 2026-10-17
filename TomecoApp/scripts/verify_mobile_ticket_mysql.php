<?php

// Uses the real normalized MySQL schema inside one outer transaction. Fictional
// tickets, officer, token, and audit rows are always rolled back, even on failure.
use App\Http\Controllers\Api\ViolationController;
use App\Http\Controllers\DashboardController;
use App\Models\User;
use App\Models\Violation;
use App\Services\TicketEvidence;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$before = Violation::count();
$paths = [];
$evidenceReferences = [];
DB::beginTransaction();
try {
    $vector = 'SIG_VECTOR_V1|1000x360|100,180;180,80;200,220;240,130;300,200;_;350,210;450,140;520,160;600,100';
    $officer = User::factory()->create(['role' => User::ROLE_OFFICER, 'signature' => $vector]);
    Auth::setUser($officer);
    $files = [];
    for ($i = 0; $i < 5; $i++) {
        $path = tempnam(sys_get_temp_dir(), 'tomeco-qa-');
        $paths[] = $path;
        $image = imagecreatetruecolor(800, 400);
        $color = imagecolorallocate($image, 40 + 25 * $i, 90 + 15 * $i, 130 + 15 * $i);
        imagefill($image, 0, 0, $color);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagestring($image, 5, 100, 180, 'FICTIONAL EVIDENCE PHOTO '.($i + 1), $white);
        imagejpeg($image, $path);
        imagedestroy($image);
        // Exercise the full accepted per-image byte size, with a valid JPEG header.
        if (in_array('--max-size', $argv, true)) {
            $file = fopen($path, 'a');
            fwrite($file, str_repeat(' ', 8 * 1024 * 1024 - filesize($path)));
            fclose($file);
        }
        $files[] = new UploadedFile($path, 'photo-'.($i + 1).'.jpg', 'image/jpeg', UPLOAD_ERR_OK, true);
    }
    $fields = [
        'enforcer_id' => 99999, 'enforcer_name' => 'Spoofed officer', 'user_id' => 99999,
        'first_name' => 'Fictional', 'middle_name' => null, 'last_name' => 'Driver',
        'license_number' => 'QA-ONLY', 'license_type' => 'non_professional',
        'plate_number' => 'OBS-123', 'registration_plate_number' => 'DOC-456',
        'vehicle_type' => 'Toyota Hilux 2.4 G Double Cab Automatic transmission pickup with a vehicle description exceeding the previous fifty-character limit',
        'vehicle_make' => 'Toyota', 'vehicle_series' => 'Hilux 2.4 G Double Cab', 'vehicle_body_type' => 'Pickup', 'vehicle_year_model' => '2024',
        'registered_owner_name' => 'Separate Fictional Registered Owner',
        'registered_owner_address' => '123 Example Road, Barangay Example, Tacloban City, Leyte, Philippines',
        'or_number' => 'OR-QA-123', 'cr_number' => 'CR-QA-456',
        'violation_type' => 'Illegal parking', 'fine_amount' => '500', 'status' => 'UNPAID',
        'location' => 'Fictional verification street', 'signature' => $vector,
    ];
    $request = Request::create(config('app.url').'/api/violations', 'POST', $fields, [], ['evidence_images' => $files], ['HTTP_ACCEPT' => 'application/json']);
    $request->setUserResolver(fn () => $officer);
    $response = app(ViolationController::class)->store($request);
    if ($response->getStatusCode() !== 201) {
        throw new RuntimeException('Submission failed.');
    }
    $ticket = Violation::findOrFail($response->getData()->data->id);
    $evidenceReferences = $ticket->evidence_photos;
    foreach ($evidenceReferences as $index => $reference) {
        if (TicketEvidence::read($reference) !== file_get_contents($paths[$index])) {
            throw new RuntimeException('Evidence order or bytes changed.');
        }
    }
    if (count($ticket->evidence_photos) !== 5 || $ticket->enforcer_id !== $officer->id || $ticket->enforcer_signature !== $vector) {
        throw new RuntimeException('Evidence or snapshot did not round trip.');
    }
    foreach (['registration_plate_number', 'or_number', 'cr_number', 'vehicle_make', 'vehicle_series', 'vehicle_body_type', 'vehicle_year_model', 'registered_owner_name', 'registered_owner_address'] as $field) {
        if ($fields[$field] !== $ticket->$field || $ticket->getRawOriginal($field) === $fields[$field]) {
            throw new RuntimeException('Registration round trip failed: '.$field);
        }
    }
    $officer->update(['signature' => 'data:image/png;base64,Y2hhbmdlZA==']);
    Auth::setUser(User::where('role', User::ROLE_ADMIN)->firstOrFail());
    $request->setUserResolver(fn () => Auth::user());
    $request->setLaravelSession($app->make('session')->driver());
    $app->instance('request', $request);
    $html = app(DashboardController::class)->violationRecord($ticket->fresh())->render();
    // Embed only fictional QA photos for the standalone browser snapshot.
    foreach ($evidenceReferences as $index => $reference) {
        $html = str_replace(route('tickets.evidence', [$ticket, $index]), 'data:image/jpeg;base64,'.base64_encode(TicketEvidence::read($reference)), $html);
    }
    if (! str_contains($html, 'Evidence photo 5 of 5') || ! str_contains($html, $fields['registered_owner_name'])) {
        throw new RuntimeException('Saved ticket did not render.');
    }
    $output = storage_path('app/private/mobile-verification');
    if (! is_dir($output)) {
        mkdir($output, 0700, true);
    }
    // Save only fictional data for browser QA; never export real ticket details.
    file_put_contents($output.'/ticket.html', $html);
    echo json_encode(['verified' => true, 'database' => DB::connection()->getDatabaseName(), 'photos' => count($ticket->evidence_photos), 'photo_bytes' => filesize($paths[0]), 'ordered' => true, 'registration_encrypted' => true, 'snapshot_preserved' => true, 'html_bytes' => strlen($html), 'outer_transaction' => 'rolled back after rendering'], JSON_PRETTY_PRINT).PHP_EOL;
} finally {
    DB::rollBack();
    foreach ($evidenceReferences as $reference) {
        TicketEvidence::delete($reference);
    }
    foreach ($paths as $path) {
        unlink($path);
    }
}
if (Violation::count() !== $before) {
    throw new RuntimeException('Existing ticket count changed.');
}
