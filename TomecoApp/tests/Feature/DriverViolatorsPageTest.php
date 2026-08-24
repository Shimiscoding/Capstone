<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverViolatorsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_view_driver_violators_page(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Violation::create([
            'driver_name' => 'Juan Dela Cruz', 'plate_number' => 'ABC 1234',
            'violation_type' => 'Illegal parking', 'fine_amount' => 500,
            'status' => 'unpaid',
        ]);

        $this->actingAs($admin)->get(route('dashboard.driver-violators'))
            ->assertOk()->assertSee('Driver Violators')->assertSee('ABC 1234')->assertSee('Illegal parking');
    }

    public function test_driver_only_sees_violations_linked_to_their_account(): void
    {
        $driver = User::factory()->create(['role' => User::ROLE_DRIVER]);
        Violation::create(['user_id' => $driver->id, 'driver_name' => $driver->fullName, 'plate_number' => 'OWN 123', 'violation_type' => 'Own violation', 'fine_amount' => 500]);
        Violation::create(['driver_name' => 'Other Driver', 'plate_number' => 'OTHER 9', 'violation_type' => 'Other violation', 'fine_amount' => 500]);

        $this->actingAs($driver)->get(route('dashboard.driver-violators'))
            ->assertOk()->assertSee('OWN 123')->assertDontSee('OTHER 9');
    }
}
