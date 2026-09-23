<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OfficerAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_officer_can_time_in_check_status_and_time_out(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $officer = User::factory()->create([
            'role' => User::ROLE_OFFICER,
            'supervisor_id' => $supervisor->id,
            'attendance_restrictions_enabled' => true,
            'attendance_time_in_start' => '00:00',
            'attendance_time_in_end' => '23:59',
            'attendance_time_out_start' => '00:00',
            'attendance_time_out_end' => '23:59',
            'attendance_working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        ]);
        Sanctum::actingAs($officer);

        $this->postJson('/api/officer/attendance/time-in', [
            'latitude' => 11.2445,
            'longitude' => 125.0031,
        ])
            ->assertCreated()
            ->assertJsonPath('attendance.date', today()->toDateString())
            ->assertJsonPath('attendance.time_in_location.latitude', 11.2445)
            ->assertJsonPath('attendance.time_in_location.longitude', 125.0031);

        $this->getJson('/api/officer/attendance/status')
            ->assertOk()
            ->assertJsonPath('status', 'timed_in');

        $this->postJson('/api/officer/attendance/time-out', [
            'latitude' => 11.2450,
            'longitude' => 125.0040,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Time out recorded successfully.')
            ->assertJsonPath('attendance.time_out_location.latitude', 11.245)
            ->assertJsonPath('attendance.time_out_location.longitude', 125.004);

        $this->getJson('/api/officer/attendance/status')
            ->assertOk()
            ->assertJsonPath('status', 'timed_out');
    }

    public function test_attendance_routes_require_authentication_and_an_officer_account(): void
    {
        $this->getJson('/api/officer/attendance/status')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->getJson('/api/officer/attendance/status')->assertForbidden();
    }
}
