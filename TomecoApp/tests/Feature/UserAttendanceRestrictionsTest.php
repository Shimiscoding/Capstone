<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserAttendanceRestrictionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_page_only_lists_enforcers_and_admin_can_save_individual_restrictions(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'fullName' => 'Table Admin']);
        User::factory()->create(['role' => User::ROLE_SUPERVISOR, 'fullName' => 'Table Supervisor']);
        $enforcer = User::factory()->create(['role' => User::ROLE_OFFICER, 'fullName' => 'Table Enforcer']);

        foreach ([[], ['type' => 'supervisor'], ['type' => 'enforcer'], ['type' => 'admin']] as $filters) {
            $this->actingAs($admin)->get(route('dashboard.attendance', $filters))
                ->assertOk()
                ->assertSeeText('Table Enforcer')
                ->assertDontSeeText('Table Supervisor')
                ->assertSee('Enforcer Attendance')
                ->assertDontSee('Supervisor Attendance')
                ->assertDontSee(route('dashboard.attendance', ['type' => 'supervisor']), false)
                ->assertSeeText('Save restrictions');
        }

        $this->actingAs($admin)->put(route('dashboard.attendance.restrictions.update', $enforcer), [
            'attendance_restrictions_enabled' => '1',
            'attendance_time_in_start' => '07:00',
            'attendance_time_in_end' => '09:00',
            'attendance_time_out_start' => '16:00',
            'attendance_time_out_end' => '18:00',
            'attendance_working_days' => ['monday', 'tuesday'],
        ])->assertSessionHas('attendance_success');

        $enforcer->refresh();
        $this->assertTrue($enforcer->attendance_restrictions_enabled);
        $this->assertSame(['monday', 'tuesday'], $enforcer->attendance_working_days);
    }

    public function test_supervisor_attendance_table_and_routes_are_removed(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);

        $this->assertFalse(Schema::hasTable('supervisor_attendances'));
        $this->assertTrue(Schema::hasTable('enforcer_attendances'));
        $this->actingAs($supervisor)->post('/dashboard/supervisor/time-in')->assertNotFound();
        $this->actingAs($supervisor)->post('/dashboard/supervisor/time-out')->assertNotFound();
        $this->actingAs($admin)->get('/dashboard/attendance/'.$supervisor->id)->assertNotFound();
        $this->actingAs($admin)->put('/dashboard/attendance/supervisors/1/time-out')->assertNotFound();
        $this->actingAs($admin)->delete('/dashboard/attendance/supervisors/1')->assertNotFound();
    }
}
