<?php

namespace Tests\Feature;

use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_violation_personal_data_is_encrypted_at_rest(): void
    {
        $vehicleType = str_repeat('Vehicle description ', 49).'test';
        $violation = Violation::create([
            'first_name' => 'Juan',
            'middle_name' => 'Dela',
            'last_name' => 'Cruz',
            'license_number' => 'N01-23-456789',
            'license_type' => 'professional',
            'plate_number' => 'ABC 1234',
            'vehicle_type' => $vehicleType,
            'or_number' => 'OR-1234567',
            'cr_number' => 'CR-7654321',
            'violation_type' => 'Illegal parking',
            'fine_amount' => 500,
            'location' => 'Main Street',
        ]);

        $stored = DB::table('Ticket_issued')->find($violation->id);

        $this->assertNotSame('Juan', $stored->first_name);
        $this->assertNotSame('Dela', $stored->middle_name);
        $this->assertNotSame('Cruz', $stored->last_name);
        $this->assertNotSame('N01-23-456789', $stored->license_number);
        $this->assertNotSame('professional', $stored->license_type);
        $this->assertNotSame($vehicleType, $stored->vehicle_type);
        $this->assertNotSame('OR-1234567', $stored->or_number);
        $this->assertNotSame('CR-7654321', $stored->cr_number);
        $this->assertNotSame('Main Street', $stored->location);
        $this->assertSame('Juan', $violation->fresh()->first_name);
        $this->assertSame('Dela', $violation->fresh()->middle_name);
        $this->assertSame('Cruz', $violation->fresh()->last_name);
        $this->assertSame('Juan Dela Cruz', $violation->fresh()->full_name);
        $this->assertSame('N01-23-456789', $violation->fresh()->license_number);
        $this->assertSame('professional', $violation->fresh()->license_type);
        $this->assertSame($vehicleType, $violation->fresh()->vehicle_type);
        $this->assertSame('OR-1234567', $violation->fresh()->or_number);
        $this->assertSame('CR-7654321', $violation->fresh()->cr_number);
        $this->assertSame('Main Street', $violation->fresh()->location);
    }

    public function test_ticket_type_migration_backfills_plaintext_and_can_be_retried(): void
    {
        $legacy = Violation::create(['plate_number' => 'LEGACY', 'fine_amount' => 100]);
        $empty = Violation::create(['plate_number' => 'EMPTY', 'fine_amount' => 100]);
        DB::table('Ticket_issued')->where('id', $legacy->id)->update([
            'license_type' => 'student_permit', 'vehicle_type' => 'Motorcycle',
        ]);
        DB::table('Ticket_issued')->where('id', $empty->id)->update(['vehicle_type' => '']);

        $migration = require database_path('migrations/2026_11_06_000000_encrypt_ticket_license_and_vehicle_types.php');
        $migration->up();

        $stored = DB::table('Ticket_issued')->find($legacy->id);
        $this->assertSame('student_permit', Crypt::decryptString($stored->license_type));
        $this->assertSame('Motorcycle', Crypt::decryptString($stored->vehicle_type));
        $this->assertSame('', $empty->fresh()->vehicle_type);
        $this->assertNull($empty->fresh()->license_type);

        $migration->up();
        $this->assertEquals($stored, DB::table('Ticket_issued')->find($legacy->id));

        $legacy->refresh()->update(['vehicle_type' => 'Sedan']);
        $updated = $legacy->fresh();
        $this->assertSame('Sedan', $updated->vehicle_type);
        $this->assertNotSame($stored->vehicle_type_key, $updated->getRawOriginal('vehicle_type_key'));
        $this->assertArrayNotHasKey('vehicle_type_key', $updated->toArray());
    }
}
