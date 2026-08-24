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
            'fullName' => 'Driver One',
            'plateNumber' => 'ABC-1234',
            'phoneNumber' => '09171234567',
            'email' => 'driver@example.com',
            'role' => User::ROLE_DRIVER,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard.users'));
        $this->assertDatabaseHas('users', [
            'email' => 'driver@example.com',
            'role' => User::ROLE_DRIVER,
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
            User::factory()->create(['role' => User::ROLE_DRIVER])
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
            User::factory()->create(['role' => User::ROLE_DRIVER])
        ));
        $selectedNotification = $admin->fresh()->unreadNotifications()->first();

        $this->actingAs($admin)
            ->post(route('dashboard.notifications.read-one', $selectedNotification->id))
            ->assertRedirect($selectedNotification->data['url']);

        $this->assertNotNull($selectedNotification->fresh()->read_at);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $otherAdmin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $otherAdmin->notify(new UserCreatedNotification(
            User::factory()->create(['role' => User::ROLE_DRIVER])
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
        $driver = User::factory()->create(['role' => User::ROLE_DRIVER]);
        $admin->notify(new UserCreatedNotification($driver));
        $admin->notify(new UserCreatedNotification($driver));
        $otherAdmin->notify(new UserCreatedNotification($driver));
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
        $driver = User::factory()->create(['role' => User::ROLE_DRIVER]);
        $admin->notify(new UserCreatedNotification($driver));
        $admin->notify(new UserCreatedNotification($driver));

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
            'role' => User::ROLE_DRIVER,
            'plateNumber' => 'XYZ-9876',
            'password' => '',
        ])->assertRedirect(route('dashboard.users'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'firstName' => 'Updated',
            'lastName' => 'User',
            'email' => 'updated@example.com',
            'role' => User::ROLE_DRIVER,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $admin->id,
            'type' => UserActivityNotification::class,
        ]);
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

    public function test_removed_badge_number_input_is_ignored(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->post(route('dashboard.users.store'), [
            'fullName' => 'Driver Without Badge',
            'badgeNumber' => 'SHOULD-BE-REMOVED',
            'plateNumber' => 'DRV-5555',
            'phoneNumber' => '09175555555',
            'email' => 'no-badge@example.com',
            'role' => User::ROLE_DRIVER,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('dashboard.users'));

        $this->assertDatabaseHas('users', [
            'email' => 'no-badge@example.com',
            'role' => User::ROLE_DRIVER,
            'plateNumber' => 'DRV-5555',
        ]);
    }

    public function test_plate_number_is_required_for_drivers_and_null_for_enforcers(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->post(route('dashboard.users.store'), [
            'fullName' => 'Driver Missing Plate',
            'phoneNumber' => '09176666666',
            'email' => 'missing-plate@example.com',
            'role' => User::ROLE_DRIVER,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('plateNumber');

        $this->actingAs($admin)->post(route('dashboard.users.store'), [
            'fullName' => 'Enforcer No Plate',
            'plateNumber' => 'SHOULD-BE-REMOVED',
            'phoneNumber' => '09177777777',
            'email' => 'enforcer@example.com',
            'role' => User::ROLE_OFFICER,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('dashboard.users'));

        $this->assertDatabaseHas('users', [
            'email' => 'enforcer@example.com',
            'plateNumber' => null,
        ]);
    }

    public function test_admin_can_search_filter_and_sort_the_user_table(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'fullName' => 'System Administrator']);
        User::factory()->create(['role' => User::ROLE_DRIVER, 'fullName' => 'Zelda Driver']);
        User::factory()->create(['role' => User::ROLE_OFFICER, 'fullName' => 'Aaron Enforcer']);
        User::factory()->create(['role' => User::ROLE_DRIVER, 'fullName' => 'Juan Santos Dela Cruz']);

        $this->actingAs($admin)
            ->get(route('dashboard.users', ['search' => 'Zelda']))
            ->assertOk()
            ->assertSee('Zelda Driver')
            ->assertDontSee('Aaron Enforcer');

        $this->actingAs($admin)
            ->get(route('dashboard.users', ['search' => 'Santos']))
            ->assertOk()
            ->assertSee('Juan Santos Dela Cruz');

        $this->actingAs($admin)
            ->get(route('dashboard.users', ['search' => 'Juan Cruz']))
            ->assertOk()
            ->assertSee('Juan Santos Dela Cruz')
            ->assertDontSee('Zelda Driver');

        $this->actingAs($admin)
            ->get(route('dashboard.users', ['role' => User::ROLE_OFFICER]))
            ->assertOk()
            ->assertSee('Aaron Enforcer')
            ->assertDontSee('Zelda Driver');

        $this->actingAs($admin)
            ->get(route('dashboard.users', ['sort' => 'name_asc']))
            ->assertOk()
            ->assertSeeInOrder(['Aaron Enforcer', 'System Administrator', 'Zelda Driver']);
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
        $this->assertSame('demo.admin@demo.tomeco.local', $demo->email);
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
        User::factory()->create(['role' => User::ROLE_OFFICER, 'fullName' => 'Other Enforcer']);

        $this->actingAs($admin)
            ->get(route('dashboard.users.supervisors.show', $supervisor))
            ->assertOk()
            ->assertSee($supervisor->fullName)
            ->assertSee($assigned->fullName)
            ->assertDontSee('Other Enforcer');
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

    public function test_admin_dashboard_shows_supervisor_attendance_table(): void
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
            ->assertSee('Time In / Time Out Records')
            ->assertSee('Attendance Supervisor')
            ->assertSee('Completed');
    }

    public function test_only_admin_can_open_the_attendance_records_page(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);

        $this->actingAs($admin)->get(route('dashboard.attendance'))->assertOk()->assertSee('Supervisor Attendance');
        $this->actingAs($supervisor)->get(route('dashboard.attendance'))->assertForbidden();
    }
}
