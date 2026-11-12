<?php

namespace Tests\Feature;

use App\Models\Violation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MobileSubmissionMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_migration_preserves_an_existing_license_date_and_adds_missing_submission_fields(): void
    {
        Schema::table('Ticket_issued', function (Blueprint $table): void {
            $table->dropColumn(['driver_address', 'accident_involved', 'driver_response', 'submission_fingerprint']);
        });
        $ticket = Violation::create([
            'first_name' => 'Fictional',
            'last_name' => 'Driver',
            'plate_number' => 'QA-123',
            'license_expiration_date' => '2030-12-03',
            'fine_amount' => 500,
            'status' => 'unpaid',
        ]);
        $before = (array) DB::table('Ticket_issued')->find($ticket->id);
        $migration = require database_path('migrations/2026_11_10_000100_add_mobile_submission_details_to_ticket_issued.php');

        $migration->up();

        $after = (array) DB::table('Ticket_issued')->find($ticket->id);
        $this->assertSame($before, array_intersect_key($after, $before));
        foreach (['driver_address', 'accident_involved', 'driver_response', 'submission_fingerprint'] as $column) {
            $this->assertArrayHasKey($column, $after);
            $this->assertNull($after[$column]);
        }

        $address = str_repeat('Fictional address, Tacloban City. ', 30);
        $ticket->update([
            'driver_address' => $address,
            'accident_involved' => false,
            'driver_response' => 'under_protest',
            'submission_fingerprint' => str_repeat('a', 64),
        ]);
        $saved = $ticket->fresh();
        $this->assertSame($address, $saved->driver_address);
        $this->assertNotSame($address, $saved->getRawOriginal('driver_address'));
        $this->assertSame('2030-12-03', $saved->license_expiration_date);
        $this->assertFalse($saved->accident_involved);
        $this->assertSame('under_protest', $saved->driver_response);
        $this->assertSame(str_repeat('a', 64), $saved->submission_fingerprint);

        $populated = (array) DB::table('Ticket_issued')->find($ticket->id);
        $migration->up();
        $this->assertSame($populated, (array) DB::table('Ticket_issued')->find($ticket->id));
        $this->assertDatabaseCount('Ticket_issued', 1);
    }
}
