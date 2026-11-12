<?php

namespace Tests\Feature;

use App\Events\OfficerLocationUpdated;
use App\Models\EnforcerAttendance;
use App\Models\OfficerLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class OfficerLocationMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitoring_page_renders_for_both_viewer_roles_with_built_assets(): void
    {
        app(Vite::class)->useHotFile(storage_path('framework/testing-location-monitoring.hot'));

        foreach ([User::ROLE_ADMIN, User::ROLE_SUPERVISOR] as $role) {
            $viewer = User::factory()->create(['role' => $role]);

            $this->actingAs($viewer)->get(route('location-monitoring.index'))
                ->assertOk()
                ->assertSee('Location Monitoring')
                ->assertSee('/build/assets/location-monitoring-', false)
                ->assertDontSee('@vite/client', false);
        }
    }

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
        $officerA->forceFill(['profile_photo_path' => 'profile-photos/test.png'])->save();
        $this->locationFor($officerA, $supervisorA);
        $this->locationFor($officerB, $supervisorB);

        $this->actingAs($supervisorA)->getJson(route('location-monitoring.officers'))
            ->assertOk()
            ->assertJsonCount(1, 'officers')
            ->assertJsonPath('officers.0.id', $officerA->id)
            ->assertJsonPath('officers.0.profile_photo_url', $officerA->profile_photo_url)
            ->assertJsonCount(1, 'teams')
            ->assertJsonPath('teams.0.id', $supervisorA->id);
        $this->actingAs($supervisorB)->getJson(route('location-monitoring.officers'))
            ->assertOk()
            ->assertJsonCount(1, 'officers')
            ->assertJsonPath('officers.0.id', $officerB->id)
            ->assertJsonCount(1, 'teams')
            ->assertJsonPath('teams.0.id', $supervisorB->id);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->getJson(route('location-monitoring.officers'))
            ->assertOk()
            ->assertJsonCount(2, 'officers')
            ->assertJsonCount(2, 'teams');
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

    public function test_officer_details_link_routes_each_viewer_to_a_page_they_can_open(): void
    {
        [$supervisor, $officer] = $this->onDutyOfficer();
        $this->locationFor($officer, $supervisor);
        $details = $this->actingAs($supervisor)->getJson(route('location-monitoring.officers'))
            ->assertOk()->json('officers.0.details_url');
        $this->get($details)->assertRedirect(route('supervisor.enforcers.attendance', $officer));
        $this->get(route('supervisor.enforcers.attendance', $officer))->assertOk();

        $otherSupervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $this->actingAs($otherSupervisor)->get($details)->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->get($details)->assertRedirect(route('dashboard.users.enforcers.show', $officer));
    }

    public function test_open_yesterday_record_does_not_authorize_location_sharing_today(): void
    {
        $this->travelTo(Carbon::parse('2026-10-08T23:50:00+08:00'));
        [$supervisor, $officer] = $this->onDutyOfficer();
        Sanctum::actingAs($officer);
        $this->postJson('/api/officer/location', ['latitude' => 11.2, 'longitude' => 125])->assertOk();

        $this->travelTo(Carbon::parse('2026-10-09T00:05:00+08:00'));
        $this->getJson('/api/officer/attendance/status')->assertJsonPath('status', 'not_timed_in');
        $this->postJson('/api/officer/location', ['latitude' => 11.3, 'longitude' => 125])
            ->assertForbidden()->assertHeader('X-Tomeco-Location-Status', 'off_duty');
        $this->postJson('/api/officer/attendance/time-in')->assertUnprocessable()
            ->assertJsonPath('message', 'Ask an administrator to correct your missed Time Out before timing in.');
        $this->assertDatabaseHas('officer_locations', ['user_id' => $officer->id, 'latitude' => 11.2]);
        $this->assertDatabaseCount('officer_location_histories', 1);

        $this->actingAs($supervisor, 'web')->getJson(route('location-monitoring.officers'))
            ->assertOk()->assertJsonPath('officers.0.status', 'off_duty')
            ->assertJsonPath('officers.0.is_sharing', false);
    }

    public function test_an_older_location_cannot_replace_a_newer_point_sent_with_a_timezone_offset(): void
    {
        $this->travelTo(Carbon::parse('2026-10-08T10:30:00Z'));
        [, $officer] = $this->onDutyOfficer();
        Sanctum::actingAs($officer);
        Event::fake([OfficerLocationUpdated::class]);
        $this->postJson('/api/officer/location', [
            'latitude' => 11.2, 'longitude' => 125, 'recorded_at' => '2026-10-08T18:29:00+08:00',
        ])->assertOk();
        $this->postJson('/api/officer/location', [
            'latitude' => 11.3, 'longitude' => 125, 'recorded_at' => '2026-10-08T10:28:59Z',
        ])->assertOk();

        $location = $officer->currentLocation()->firstOrFail();
        $this->assertSame(11.2, $location->latitude);
        $this->assertSame(Carbon::parse('2026-10-08T10:29:00Z')->getTimestamp(), $location->recorded_at->getTimestamp());
        $this->assertDatabaseCount('officer_location_histories', 1);
        Event::assertDispatchedTimes(OfficerLocationUpdated::class, 1);
    }

    public function test_broadcast_failure_does_not_turn_confirmed_location_or_time_out_into_an_error(): void
    {
        $this->travelTo(Carbon::parse('2026-10-08T18:00:00+08:00'));
        [, $officer] = $this->onDutyOfficer();
        Sanctum::actingAs($officer);
        Event::listen(OfficerLocationUpdated::class, fn () => throw new RuntimeException('Broadcast unavailable.'));

        $this->postJson('/api/officer/location', ['latitude' => 11.2, 'longitude' => 125])->assertOk();
        $this->postJson('/api/officer/attendance/time-out')->assertOk();

        $this->assertFalse($officer->currentLocation()->firstOrFail()->is_sharing);
        $this->assertNotNull($officer->enforcerAttendances()->firstOrFail()->time_out);
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
        EnforcerAttendance::create(['user_id' => $officer->id, 'supervisor_id' => $supervisor->id, 'attendance_date' => today('Asia/Manila')->toDateString(), 'time_in' => now()]);

        return [$supervisor, $officer];
    }

    private function locationFor(User $officer, User $supervisor): OfficerLocation
    {
        return OfficerLocation::create(['user_id' => $officer->id, 'team_id' => $supervisor->id, 'latitude' => 11.2445, 'longitude' => 125.0031, 'accuracy' => 7, 'is_sharing' => true, 'recorded_at' => now()]);
    }
}
