<?php

namespace Tests\Feature;

use App\Models\SupervisorAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UserAttendanceRestrictionsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_attendance_page_separates_users_by_role_and_admin_can_save_individual_restrictions(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'fullName' => 'Table Admin']);
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR, 'fullName' => 'Table Supervisor']);
        $enforcer = User::factory()->create(['role' => User::ROLE_OFFICER, 'fullName' => 'Table Enforcer']);

        $this->actingAs($admin)->get(route('dashboard.attendance', ['type' => 'supervisor']))
            ->assertOk()
            ->assertSeeText('Table Supervisor')
            ->assertDontSeeText('Table Enforcer')
            ->assertSeeText('Save restrictions');
        $this->actingAs($admin)->get(route('dashboard.attendance', ['type' => 'enforcer']))
            ->assertOk()
            ->assertSeeText('Table Enforcer')
            ->assertDontSeeText('Table Supervisor');
        $this->actingAs($admin)->get(route('dashboard.attendance', ['type' => 'admin']))
            ->assertOk()
            ->assertSeeText('Table Admin')
            ->assertDontSeeText('Table Supervisor');

        $this->actingAs($admin)->put(route('dashboard.attendance.restrictions.update', $supervisor), [
            'attendance_restrictions_enabled' => '1',
            'attendance_time_in_start' => '07:00',
            'attendance_time_in_end' => '09:00',
            'attendance_time_out_start' => '16:00',
            'attendance_time_out_end' => '18:00',
            'attendance_working_days' => ['monday', 'tuesday'],
        ])->assertSessionHas('attendance_success');

        $supervisor->refresh();
        $this->assertTrue($supervisor->attendance_restrictions_enabled);
        $this->assertSame(['monday', 'tuesday'], $supervisor->attendance_working_days);
    }

    public function test_supervisor_time_in_uses_their_individual_restriction(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-17 10:00:00', 'Asia/Manila'));
        $supervisor = User::factory()->create([
            'role' => User::ROLE_SUPERVISOR,
            'attendance_restrictions_enabled' => true,
            'attendance_time_in_start' => '07:00',
            'attendance_time_in_end' => '09:00',
            'attendance_time_out_start' => '16:00',
            'attendance_time_out_end' => '18:00',
            'attendance_working_days' => ['monday'],
        ]);

        $this->actingAs($supervisor)->post(route('supervisor.time-in'))
            ->assertSessionHas('attendance_error', 'Time In is only allowed from 07:00 AM to 09:00 AM.');

        $this->assertSame(0, SupervisorAttendance::where('user_id', $supervisor->id)->count());
    }
}
