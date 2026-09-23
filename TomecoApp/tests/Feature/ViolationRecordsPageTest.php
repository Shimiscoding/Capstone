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
        Violation::create([
            'first_name' => 'Juan', 'middle_name' => 'Dela', 'last_name' => 'Cruz',
            'license_number' => 'N01-23-456789',
            'license_type' => 'professional', 'plate_number' => 'ABC 1234',
            'or_number' => 'OR-1234567', 'cr_number' => 'CR-7654321',
            'violation_type' => 'Illegal parking', 'fine_amount' => 500,
            'status' => 'unpaid',
        ]);

        $this->actingAs($admin)->get(route('dashboard.violation-records'))
            ->assertOk()
            ->assertSee('Violation Records')
            ->assertSee('ABC 1234')
            ->assertSee('OR-1234567')
            ->assertSee('CR-7654321')
            ->assertSee('Professional')
            ->assertSee(route('dashboard.violation-records.show', 1))
            ->assertSee('Illegal parking');

        $this->actingAs($admin)->get(route('dashboard.violation-records.show', 1))
            ->assertOk()
            ->assertSee('Violation ticket #0000001')
            ->assertSee('Motorist and permit')
            ->assertSee('Vehicle documents')
            ->assertSee('OR-1234567')
            ->assertSee('CR-7654321');
    }

}
