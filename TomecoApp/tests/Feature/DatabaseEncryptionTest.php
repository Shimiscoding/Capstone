<?php

namespace Tests\Feature;

use App\Models\ImpoundedVehicle;
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
            'driver_name' => 'Juan Dela Cruz',
            'license_number' => 'N01-23-456789',
            'plate_number' => 'ABC 1234',
            'violation_type' => 'Illegal parking',
            'fine_amount' => 500,
            'location' => 'Main Street',
        ]);

        $stored = DB::table('violations')->find($violation->id);

        $this->assertNotSame('Juan Dela Cruz', $stored->driver_name);
        $this->assertNotSame('N01-23-456789', $stored->license_number);
        $this->assertNotSame('Main Street', $stored->location);
        $this->assertSame('Juan Dela Cruz', $violation->fresh()->driver_name);
        $this->assertSame('N01-23-456789', $violation->fresh()->license_number);
        $this->assertSame('Main Street', $violation->fresh()->location);
    }

    public function test_impound_personal_data_is_encrypted_at_rest(): void
    {
        $vehicle = ImpoundedVehicle::create([
            'reference' => 'IMP-2026-0001',
            'owner' => 'Maria Santos',
            'vehicle' => 'Toyota Vios',
            'type' => 'Car',
            'plate' => 'XYZ 9876',
            'violation' => 'Road obstruction',
            'impounded_at' => '2026-08-03',
            'location' => 'Main Yard',
            'status' => 'Impounded',
        ]);

        $stored = DB::table('impounded_vehicles')->find($vehicle->id);

        $this->assertNotSame('Maria Santos', $stored->owner);
        $this->assertNotSame('Main Yard', $stored->location);
        $this->assertSame('Maria Santos', $vehicle->fresh()->owner);
        $this->assertSame('Main Yard', $vehicle->fresh()->location);
    }
}
