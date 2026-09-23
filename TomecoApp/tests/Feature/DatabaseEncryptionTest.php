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
            'first_name' => 'Juan',
            'middle_name' => 'Dela',
            'last_name' => 'Cruz',
            'license_number' => 'N01-23-456789',
            'license_type' => 'professional',
            'plate_number' => 'ABC 1234',
            'or_number' => 'OR-1234567',
            'cr_number' => 'CR-7654321',
            'violation_type' => 'Illegal parking',
            'fine_amount' => 500,
            'location' => 'Main Street',
        ]);

        $stored = DB::table('violations')->find($violation->id);

        $this->assertNotSame('Juan', $stored->first_name);
        $this->assertNotSame('Dela', $stored->middle_name);
        $this->assertNotSame('Cruz', $stored->last_name);
        $this->assertNotSame('N01-23-456789', $stored->license_number);
        $this->assertNotSame('OR-1234567', $stored->or_number);
        $this->assertNotSame('CR-7654321', $stored->cr_number);
        $this->assertNotSame('Main Street', $stored->location);
        $this->assertSame('Juan', $violation->fresh()->first_name);
        $this->assertSame('Dela', $violation->fresh()->middle_name);
        $this->assertSame('Cruz', $violation->fresh()->last_name);
        $this->assertSame('Juan Dela Cruz', $violation->fresh()->full_name);
        $this->assertSame('N01-23-456789', $violation->fresh()->license_number);
        $this->assertSame('OR-1234567', $violation->fresh()->or_number);
        $this->assertSame('CR-7654321', $violation->fresh()->cr_number);
        $this->assertSame('Main Street', $violation->fresh()->location);
    }

}
