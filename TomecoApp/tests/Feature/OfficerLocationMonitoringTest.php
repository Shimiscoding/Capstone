<?php

namespace Tests\Feature;

use App\Events\OfficerLocationUpdated;
use App\Models\EnforcerAttendance;
use App\Models\OfficerLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OfficerLocationMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_timed_in_officer_can_update_one_current_location_and_throttled_history(): void
    {
        Event::fake([OfficerLocationUpdated::class]);
        [$supervisor, $officer] = $this->onDutyOfficer();
        Sanctum::actingAs($officer);

        $this->postJson('/api/officer/location', ['latitude' => 11.2445, 'longitude' => 125.0031, 'accuracy' => 7.5, 'speed' => 3.2, 'heading' => 110])->assertOk();
        $this->postJson('/api/officer/location', ['latitude' => 11.24451, 'longitude' => 125.00311])->assertOk();

        $this->assertDatabaseCount('officer_locations', 1);
        $this->assertDatabaseCount('officer_location_histories', 1);
        $this->assertDatabaseHas('officer_locations', ['user_id' => $officer->id, 'team_id' => $supervisor->id, 'is_sharing' => true]);
        Event::assertDispatchedTimes(OfficerLocationUpdated::class, 2);
    }

    public function test_off_duty_officer_and_non_officer_cannot_submit_location(): void
    {
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER]);
        Sanctum::actingAs($officer);
        $this->postJson('/api/officer/location', ['latitude' => 11.24, 'longitude' => 125.00])->assertForbidden();

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Sanctum::actingAs($admin);
        $this->postJson('/api/officer/location', ['latitude' => 11.24, 'longitude' => 125.00])->assertForbidden();
        $this->assertDatabaseCount('officer_locations', 0);
    }

    public function test_invalid_coordinates_are_rejected(): void
    {
        [, $officer] = $this->onDutyOfficer();
        Sanctum::actingAs($officer);
        $this->postJson('/api/officer/location', ['latitude' => 91, 'longitude' => -181, 'accuracy' => -1])->assertUnprocessable()->assertJsonValidationErrors(['latitude', 'longitude', 'accuracy']);
    }

    public function test_supervisor_only_receives_their_teams_locations_while_admin_receives_all(): void
    {
        [$supervisorA, $officerA] = $this->onDutyOfficer();
        [$supervisorB, $officerB] = $this->onDutyOfficer();
        $this->locationFor($officerA, $supervisorA);
        $this->locationFor($officerB, $supervisorB);

        $this->actingAs($supervisorA)->getJson(route('location-monitoring.officers'))->assertOk()->assertJsonCount(1, 'officers')->assertJsonPath('officers.0.id', $officerA->id);
        $this->actingAs($supervisorB)->getJson(route('location-monitoring.officers'))->assertOk()->assertJsonCount(1, 'officers')->assertJsonPath('officers.0.id', $officerB->id);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->getJson(route('location-monitoring.officers'))->assertOk()->assertJsonCount(2, 'officers');
    }

    public function test_time_out_stops_location_sharing(): void
    {
        Event::fake([OfficerLocationUpdated::class]);
        [$supervisor, $officer] = $this->onDutyOfficer();
        $this->locationFor($officer, $supervisor);

        $this->actingAs($supervisor)->post(route('supervisor.enforcers.time-out', $officer))->assertRedirect();
        $this->assertFalse(OfficerLocation::where('user_id', $officer->id)->firstOrFail()->is_sharing);
        Event::assertDispatched(OfficerLocationUpdated::class);
    }

    private function onDutyOfficer(): array
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $officer = User::factory()->create([
            'role' => User::ROLE_OFFICER,
            'supervisor_id' => $supervisor->id,
            'account_status' => User::STATUS_ACTIVE,
            'attendance_restrictions_enabled' => true,
            'attendance_time_in_start' => '00:00',
            'attendance_time_in_end' => '23:59',
            'attendance_time_out_start' => '00:00',
            'attendance_time_out_end' => '23:59',
            'attendance_working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        ]);
        EnforcerAttendance::create(['user_id' => $officer->id, 'supervisor_id' => $supervisor->id, 'attendance_date' => today(), 'time_in' => now()]);

        return [$supervisor, $officer];
    }

    private function locationFor(User $officer, User $supervisor): OfficerLocation
    {
        return OfficerLocation::create(['user_id' => $officer->id, 'team_id' => $supervisor->id, 'latitude' => 11.2445, 'longitude' => 125.0031, 'accuracy' => 7, 'is_sharing' => true, 'recorded_at' => now()]);
    }
}
