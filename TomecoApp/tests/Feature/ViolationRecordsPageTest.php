<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViolationRecordsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_view_violation_records_page(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $firstViolation = Violation::create([
            'first_name' => 'Juan', 'middle_name' => 'Dela', 'last_name' => 'Cruz',
            'license_number' => 'N01-23-456789',
            'license_type' => 'professional', 'plate_number' => 'ABC 1234',
            'or_number' => 'OR-1234567', 'cr_number' => 'CR-7654321',
            'violation_type' => 'Illegal parking', 'fine_amount' => 500,
            'status' => 'unpaid',
        ]);
        $secondViolation = Violation::create([
            'first_name' => 'Juan', 'middle_name' => 'Dela', 'last_name' => 'Cruz',
            'license_number' => 'N0123456789',
            'license_type' => 'professional', 'plate_number' => 'XYZ 9876',
            'or_number' => 'OR-7654321', 'cr_number' => 'CR-1234567',
            'violation_type' => 'Obstruction', 'fine_amount' => 750,
            'status' => 'paid',
        ]);

        $this->actingAs($admin)->get(route('dashboard.violation-records'))
            ->assertOk()
            ->assertSee('Violation Records')
            ->assertSee('ABC 1234')
            ->assertSee('Actions')
            ->assertSee('View complete ticket')
            ->assertSee(route('dashboard.violation-records.motorist', $firstViolation))
            ->assertSee(route('dashboard.violation-records.show', 1))
            ->assertSee('Illegal parking');

        $this->actingAs($admin)->get(route('dashboard.violation-records.motorist', $firstViolation))
            ->assertOk()
            ->assertSee('Driver violation history')
            ->assertSee('Juan Dela Cruz')
            ->assertSee('2 violations')
            ->assertSee('Illegal parking')
            ->assertSee('Obstruction')
            ->assertSee('XYZ 9876')
            ->assertSee(route('dashboard.violation-records.show', $secondViolation));

        $this->actingAs($admin)->get(route('dashboard.violation-records.show', 1))
            ->assertOk()
            ->assertSee('Violation ticket #0000001')
            ->assertSee('Driver and permit')
            ->assertSee('Vehicle documents')
            ->assertSee('OR-1234567')
            ->assertSee('CR-7654321');
    }
}
