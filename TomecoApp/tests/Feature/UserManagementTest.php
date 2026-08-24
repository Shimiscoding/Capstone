<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\UserActivityNotification;
use App\Notifications\UserCreatedNotification;
use App\Models\SupervisorAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_user_with_a_role(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin)->post(route('dashboard.users.store'), [
            'fullName' => 'Enforcer One',
            'phoneNumber' => '09171234567',
            'email' => 'enforcer-one@example.com',
            'role' => User::ROLE_OFFICER,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard.users'));
        $this->assertDatabaseHas('users', [
            'email' => 'enforcer-one@example.com',
            'role' => User::ROLE_OFFICER,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $admin->id,
            'type' => UserCreatedNotification::class,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $admin->id,
            'read_at' => null,
        ]);
    }

    public function test_admin_can_mark_all_notifications_as_read(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $admin->notify(new UserCreatedNotification(
            User::factory()->create(['role' => User::ROLE_OFFICER])
        ));

        $this->actingAs($admin)
            ->post(route('dashboard.notifications.read'))
            ->assertRedirect()
            ->assertSessionMissing('success');

        $this->assertSame(0, $admin->fresh()->unreadNotifications()->count());
    }

    public function test_selecting_a_notification_marks_only_that_notification_as_read(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $admin->notify(new UserCreatedNotification(
            User::factory()->create(['role' => User::ROLE_OFFICER])
        ));
        $selectedNotification = $admin->fresh()->unreadNotifications()->first();

        $this->actingAs($admin)
            ->post(route('dashboard.notifications.read-one', $selectedNotification->id))
            ->assertRedirect($selectedNotification->data['url']);

        $this->assertNotNull($selectedNotification->fresh()->read_at);
    }

    public function test_old_absolute_notification_url_redirects_on_the_current_host(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $admin->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => UserCreatedNotification::class,
            'data' => [
                'title' => 'Old notification',
                'message' => 'Created on another host.',
                'url' => 'http://127.0.0.1:8000/dashboard/users?search=Jose%20Dela%20Cruz%20Sr.',
            ],
        ]);
        $notification = $admin->fresh()->notifications()->first();

        $this->actingAs($admin)
            ->post(route('dashboard.notifications.read-one', $notification->id))
            ->assertRedirect('/dashboard/users?search=Jose%20Dela%20Cruz%20Sr.');
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $otherAdmin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $otherAdmin->notify(new UserCreatedNotification(
            User::factory()->create(['role' => User::ROLE_OFFICER])
        ));
        $notification = $otherAdmin->fresh()->unreadNotifications()->first();

        $this->actingAs($admin)
            ->post(route('dashboard.notifications.read-one', $notification->id))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_user_can_delete_selected_notifications_without_deleting_another_users_notifications(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $otherAdmin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER]);
        $admin->notify(new UserCreatedNotification($officer));
        $admin->notify(new UserCreatedNotification($officer));
        $otherAdmin->notify(new UserCreatedNotification($officer));
        $selected = $admin->fresh()->notifications()->first();
        $otherNotification = $otherAdmin->fresh()->notifications()->first();

        $this->actingAs($admin)->delete(route('dashboard.notifications.delete'), [
            'notifications' => [$selected->id, $otherNotification->id],
        ])->assertRedirect();

        $this->assertDatabaseMissing('notifications', ['id' => $selected->id]);
        $this->assertDatabaseHas('notifications', ['id' => $otherNotification->id]);
        $this->assertSame(1, $admin->fresh()->notifications()->count());
    }

    public function test_user_can_delete_all_their_notifications(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER]);
        $admin->notify(new UserCreatedNotification($officer));
        $admin->notify(new UserCreatedNotification($officer));

        $this->actingAs($admin)
            ->delete(route('dashboard.notifications.delete-all'))
            ->assertRedirect();

        $this->assertSame(0, $admin->fresh()->notifications()->count());
    }

    public function test_non_admin_cannot_manage_users(): void
    {
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER]);

        $this->actingAs($officer)->get(route('dashboard.users'))->assertForbidden();
        $this->actingAs($officer)->get(route('dashboard.users.create'))->assertForbidden();
    }

    public function test_an_unknown_role_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->post(route('dashboard.users.store'), [
            'fullName' => 'Unknown Role',
            'phoneNumber' => '09170000000',
            'email' => 'unknown@example.com',
            'role' => 'superuser',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'unknown@example.com']);
    }

    public function test_admin_can_edit_and_update_a_user(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create(['role' => User::ROLE_OFFICER]);

        $this->actingAs($admin)
            ->get(route('dashboard.users.edit', $user))
            ->assertOk()
            ->assertSee('value="'.$user->firstName.'"', false)
            ->assertSee('value="'.$user->lastName.'"', false);

        $this->actingAs($admin)->put(route('dashboard.users.update', $user), [
            'firstName' => 'Updated',
            'middleName' => '',
            'lastName' => 'User',
            'nameExtension' => '',
            'phoneNumber' => $user->phoneNumber,
            'email' => 'updated@example.com',
            'role' => User::ROLE_SUPERVISOR,
            'password' => '',
        ])->assertRedirect(route('dashboard.users'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'firstName' => 'Updated',
            'lastName' => 'User',
            'email' => 'updated@example.com',
            'role' => User::ROLE_SUPERVISOR,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $admin->id,
            'type' => UserActivityNotification::class,
        ]);
    }

    public function test_edit_back_link_and_update_return_to_the_originating_user_table(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);

        $allUsersEdit = $this->actingAs($admin)
            ->get(route('dashboard.users.edit', ['user' => $supervisor, 'from' => 'users']))
            ->assertOk()
            ->assertSee('href="'.route('dashboard.users').'">Back', false)
            ->assertSee('href="'.route('dashboard.users').'">Cancel', false)
            ->assertDontSee('name="user_section"', false);

        $this->actingAs($admin)->put(route('dashboard.users.update', $supervisor), [
            'firstName' => $supervisor->firstName,
            'middleName' => $supervisor->middleName,
            'lastName' => $supervisor->lastName,
            'nameExtension' => $supervisor->nameExtension,
            'phoneNumber' => $supervisor->phoneNumber,
            'email' => $supervisor->email,
            'role' => User::ROLE_SUPERVISOR,
            'password' => '',
        ])->assertRedirect(route('dashboard.users'));

        $supervisorEdit = $this->actingAs($admin)
            ->get(route('dashboard.users.edit', ['user' => $supervisor, 'from' => 'supervisors']))
            ->assertOk()
            ->assertSee('href="'.route('dashboard.users.supervisors').'">Back', false)
            ->assertSee('href="'.route('dashboard.users.supervisors').'">Cancel', false)
            ->assertSee('name="user_section" value="supervisors"', false);
    }

    public function test_editing_from_supervisor_details_returns_to_the_same_supervisor(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $detailRoute = route('dashboard.users.supervisors.show', $supervisor);

        $this->actingAs($admin)
            ->get(route('dashboard.users.edit', [
                'user' => $supervisor,
                'from' => 'supervisors',
                'return_to' => 'detail',
            ]))
            ->assertOk()
            ->assertSee('href="'.$detailRoute.'">Back', false)
            ->assertSee('href="'.$detailRoute.'">Cancel', false)
            ->assertSee('name="return_to" value="detail"', false);

        $this->actingAs($admin)->put(route('dashboard.users.update', $supervisor), [
            'firstName' => $supervisor->firstName,
            'middleName' => $supervisor->middleName,
            'lastName' => $supervisor->lastName,
            'nameExtension' => $supervisor->nameExtension,
            'phoneNumber' => $supervisor->phoneNumber,
            'email' => $supervisor->email,
            'role' => User::ROLE_SUPERVISOR,
            'user_section' => 'supervisors',
            'return_to' => 'detail',
        ])->assertRedirect($detailRoute);
    }

    public function test_admin_can_delete_another_user_but_not_themselves(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->delete(route('dashboard.users.destroy', $user))
            ->assertRedirect(route('dashboard.users'));
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $admin->id,
            'type' => UserActivityNotification::class,
        ]);

        $this->actingAs($admin)
            ->delete(route('dashboard.users.destroy', $admin))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_can_search_filter_and_sort_the_user_table(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'fullName' => 'System Administrator']);
        User::factory()->create(['role' => User::ROLE_SUPERVISOR, 'fullName' => 'Zelda Supervisor']);
        User::factory()->create(['role' => User::ROLE_OFFICER, 'fullName' => 'Aaron Enforcer']);
        User::factory()->create(['role' => User::ROLE_SUPERVISOR, 'fullName' => 'Juan Santos Dela Cruz']);

        $this->actingAs($admin)
            ->get(route('dashboard.users', ['search' => 'Zelda']))
            ->assertOk()
            ->assertSee('Zelda Supervisor')
            ->assertDontSee('Aaron Enforcer');

        $this->actingAs($admin)
            ->get(route('dashboard.users', ['search' => 'Santos']))
            ->assertOk()
            ->assertSee('Juan Santos Dela Cruz');

        $this->actingAs($admin)
            ->get(route('dashboard.users', ['search' => 'Juan Cruz']))
            ->assertOk()
            ->assertSee('Juan Santos Dela Cruz')
            ->assertDontSee('Zelda Supervisor');

        $this->actingAs($admin)
            ->get(route('dashboard.users', ['role' => User::ROLE_OFFICER]))
            ->assertOk()
            ->assertSee('Aaron Enforcer')
            ->assertDontSee('Zelda Supervisor');

        $this->actingAs($admin)
            ->get(route('dashboard.users', ['sort' => 'name_asc']))
            ->assertOk()
            ->assertSeeInOrder(['Aaron Enforcer', 'System Administrator', 'Zelda Supervisor']);
    }

    public function test_non_admin_cannot_update_or_delete_users(): void
    {
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER]);
        $user = User::factory()->create();

        $this->actingAs($officer)->get(route('dashboard.users.edit', $user))->assertForbidden();
        $this->actingAs($officer)->put(route('dashboard.users.update', $user), [])->assertForbidden();
        $this->actingAs($officer)->delete(route('dashboard.users.destroy', $user))->assertForbidden();
    }

    public function test_phone_number_must_be_an_eleven_digit_philippine_mobile_number(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->post(route('dashboard.users.store'), [
            'firstName' => 'Invalid',
            'lastName' => 'Phone',
            'phoneNumber' => '091234567890',
            'email' => 'invalid-phone@example.com',
            'role' => User::ROLE_OFFICER,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('phoneNumber');
    }

    public function test_admin_can_create_a_verified_demo_account_without_an_email(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->post(route('dashboard.users.store'), [
            'firstName' => 'Demo',
            'lastName' => 'Admin',
            'username' => 'demo.admin',
            'address' => 'Demo Street',
            'area' => 'Demo Area',
            'barangay' => 'Demo Barangay',
            'phoneNumber' => '09178889999',
            'role' => User::ROLE_ADMIN,
            'demo_account' => '1',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('dashboard.users'));

        $demo = User::where('username', 'demo.admin')->firstOrFail();
        $this->assertSame('demo.admin@demo.com', $demo->email);
        $this->assertNotNull($demo->email_verified_at);
    }

    public function test_supervisor_details_show_only_their_assigned_enforcers(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $assigned = User::factory()->create([
            'role' => User::ROLE_OFFICER,
            'supervisor_id' => $supervisor->id,
            'fullName' => 'Assigned Enforcer',
        ]);
        $otherSupervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        User::factory()->create([
            'role' => User::ROLE_OFFICER,
            'supervisor_id' => $otherSupervisor->id,
            'fullName' => 'Other Enforcer',
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard.users.supervisors.show', $supervisor))
            ->assertOk()
            ->assertSee($supervisor->fullName)
            ->assertSee($assigned->fullName)
            ->assertDontSee('Other Enforcer');
    }

    public function test_admin_can_add_and_remove_enforcers_from_a_supervisor_details_page(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR, 'fullName' => 'Team Supervisor']);
        $available = User::factory()->create([
            'role' => User::ROLE_OFFICER,
            'supervisor_id' => null,
            'fullName' => 'Available Enforcer',
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard.users.supervisors.show', $supervisor))
            ->assertOk()
            ->assertSee('Add enforcer')
            ->assertSee('Search by name')
            ->assertSee('Available Enforcer')
            ->assertDontSee('href="'.route('dashboard.users.edit', ['user' => $available, 'from' => 'enforcers']).'"', false);

        $this->actingAs($admin)
            ->post(route('dashboard.users.supervisors.enforcers.assign', [$supervisor, $available]))
            ->assertRedirect();
        $this->assertSame($supervisor->id, $available->fresh()->supervisor_id);

        $this->actingAs($admin)
            ->delete(route('dashboard.users.supervisors.enforcers.remove', [$supervisor, $available]))
            ->assertRedirect();
        $this->assertNull($available->fresh()->supervisor_id);
    }

    public function test_non_admin_cannot_manage_a_supervisors_enforcers(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $otherSupervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $enforcer = User::factory()->create(['role' => User::ROLE_OFFICER, 'supervisor_id' => null]);

        $this->actingAs($otherSupervisor)
            ->post(route('dashboard.users.supervisors.enforcers.assign', [$supervisor, $enforcer]))
            ->assertForbidden();
        $this->assertNull($enforcer->fresh()->supervisor_id);
    }

    public function test_enforcer_and_admin_detail_pages_show_their_information(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'fullName' => 'Detail Admin']);
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR, 'fullName' => 'Team Supervisor']);
        $enforcer = User::factory()->create([
            'role' => User::ROLE_OFFICER,
            'supervisor_id' => $supervisor->id,
            'fullName' => 'Detail Enforcer',
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard.users.enforcers.show', $enforcer))
            ->assertOk()
            ->assertSee('Detail Enforcer')
            ->assertSee('Team Supervisor');

        $this->actingAs($admin)
            ->get(route('dashboard.users.admins.show', $admin))
            ->assertOk()
            ->assertSee('Detail Admin');
    }

    public function test_supervisor_has_a_team_landing_page_without_admin_privileges(): void
    {
        $supervisor = User::factory()->create([
            'role' => User::ROLE_SUPERVISOR,
            'firstName' => 'Team',
            'lastName' => 'Lead',
        ]);
        User::factory()->create([
            'role' => User::ROLE_OFFICER,
            'supervisor_id' => $supervisor->id,
            'fullName' => 'Assigned Officer',
            'barangay' => 'Barangay One',
            'area' => 'Area One',
        ]);

        $this->actingAs($supervisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Supervisor workspace')
            ->assertSee('Assigned Officer')
            ->assertDontSee('Manage users');

        $this->actingAs($supervisor)
            ->get(route('dashboard.users.supervisors'))
            ->assertForbidden();
    }

    public function test_supervisor_can_time_in_and_time_out_only_once_per_day(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);

        $this->actingAs($supervisor)->post(route('supervisor.time-in'))->assertRedirect();
        $attendance = SupervisorAttendance::where('user_id', $supervisor->id)->firstOrFail();
        $this->assertNotNull($attendance->time_in);
        $this->assertNull($attendance->time_out);

        $this->actingAs($supervisor)->post(route('supervisor.time-out'))->assertRedirect();
        $this->assertNotNull($attendance->fresh()->time_out);
        $this->assertSame(1, SupervisorAttendance::where('user_id', $supervisor->id)->count());

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->post(route('supervisor.time-in'))->assertForbidden();
    }

    public function test_admin_dashboard_does_not_show_supervisor_attendance_table(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR, 'fullName' => 'Attendance Supervisor']);
        SupervisorAttendance::create([
            'user_id' => $supervisor->id,
            'attendance_date' => today(),
            'time_in' => now()->subHours(8),
            'time_out' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Time In / Time Out Records')
            ->assertDontSee('Attendance Supervisor');
    }

    public function test_only_admin_can_open_the_attendance_records_page(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);

        $this->actingAs($admin)->get(route('dashboard.attendance'))->assertOk()->assertSee('Supervisor Attendance');
        $this->actingAs($supervisor)->get(route('dashboard.attendance'))->assertForbidden();
    }

    public function test_attendance_page_lists_each_supervisor_only_once(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR, 'fullName' => 'Unique Attendance User']);
        SupervisorAttendance::create(['user_id' => $supervisor->id, 'attendance_date' => today()->subDay(), 'time_in' => now()->subDay()]);
        SupervisorAttendance::create(['user_id' => $supervisor->id, 'attendance_date' => today(), 'time_in' => now()]);

        $this->actingAs($admin)
            ->get(route('dashboard.attendance'))
            ->assertOk()
            ->assertSeeText('Unique Attendance User', false)
            ->assertSeeText('2')
            ->assertSeeText('View details')
            ->assertSeeTextInOrder(['Unique Attendance User', '2']);

        $response = $this->actingAs($admin)->get(route('dashboard.attendance'));
        $this->assertSame(1, substr_count($response->getContent(), 'Unique Attendance User'));
    }

    public function test_admin_can_open_all_timestamps_for_a_specific_supervisor(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR, 'fullName' => 'Timestamp Supervisor']);
        SupervisorAttendance::create(['user_id' => $supervisor->id, 'attendance_date' => today(), 'time_in' => now()->subHour(), 'time_out' => now()]);

        $this->actingAs($admin)
            ->get(route('dashboard.attendance.supervisor', $supervisor))
            ->assertOk()
            ->assertSee('Timestamp Supervisor')
            ->assertSee('Complete Time In and Time Out timestamp history')
            ->assertSee('Completed');

        $this->actingAs($supervisor)
            ->get(route('dashboard.attendance.supervisor', $supervisor))
            ->assertForbidden();
    }

    public function test_supervisor_can_manage_only_available_enforcers_on_their_team(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $otherSupervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $available = User::factory()->create(['role' => User::ROLE_OFFICER, 'supervisor_id' => null, 'fullName' => 'Available Enforcer']);
        $taken = User::factory()->create(['role' => User::ROLE_OFFICER, 'supervisor_id' => $otherSupervisor->id]);

        $this->actingAs($supervisor)
            ->get(route('supervisor.team'))
            ->assertOk()
            ->assertSee('Available Enforcer')
            ->assertSee('Search by name')
            ->assertSee('teamEnforcerSearch');
        $this->actingAs($supervisor)->post(route('supervisor.team.assign', $available))->assertRedirect();
        $this->assertSame($supervisor->id, $available->fresh()->supervisor_id);

        $this->actingAs($supervisor)->post(route('supervisor.team.assign', $taken))->assertRedirect();
        $this->assertSame($otherSupervisor->id, $taken->fresh()->supervisor_id);

        $this->actingAs($supervisor)->delete(route('supervisor.team.remove', $available))->assertRedirect();
        $this->assertNull($available->fresh()->supervisor_id);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->get(route('supervisor.team'))->assertForbidden();
    }
}
