<?php

namespace Tests\Feature;

use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_violation_personal_data_is_encrypted_at_rest(): void
    {
        $violation = Violation::create([
            'motorist_name' => 'Juan Dela Cruz',
            'license_number' => 'N01-23-456789',
            'plate_number' => 'ABC 1234',
            'violation_type' => 'Illegal parking',
            'fine_amount' => 500,
            'location' => 'Main Street',
        ]);

        $stored = DB::table('violations')->find($violation->id);

        $this->assertNotSame('Juan Dela Cruz', $stored->motorist_name);
        $this->assertNotSame('N01-23-456789', $stored->license_number);
        $this->assertNotSame('Main Street', $stored->location);
        $this->assertSame('Juan Dela Cruz', $violation->fresh()->motorist_name);
        $this->assertSame('N01-23-456789', $violation->fresh()->license_number);
        $this->assertSame('Main Street', $violation->fresh()->location);
    }

}
