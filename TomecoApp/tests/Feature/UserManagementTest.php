<?php

namespace Tests\Feature;

use App\Models\User;
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
            'badgeNumber' => 'DRV-001',
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
            'type' => \App\Notifications\UserCreatedNotification::class,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $admin->id,
            'read_at' => null,
        ]);
    }

    public function test_admin_can_mark_all_notifications_as_read(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $admin->notify(new \App\Notifications\UserCreatedNotification(
            User::factory()->create(['role' => User::ROLE_DRIVER, 'badgeNumber' => null])
        ));

        $this->actingAs($admin)
            ->post(route('dashboard.notifications.read'))
            ->assertRedirect();

        $this->assertSame(0, $admin->fresh()->unreadNotifications()->count());
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
            'badgeNumber' => 'UNK-001',
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
            ->assertSee($user->fullName);

        $this->actingAs($admin)->put(route('dashboard.users.update', $user), [
            'fullName' => 'Updated User',
            'badgeNumber' => $user->badgeNumber,
            'phoneNumber' => $user->phoneNumber,
            'email' => 'updated@example.com',
            'role' => User::ROLE_DRIVER,
            'plateNumber' => 'XYZ-9876',
            'password' => '',
        ])->assertRedirect(route('dashboard.users'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'fullName' => 'Updated User',
            'email' => 'updated@example.com',
            'role' => User::ROLE_DRIVER,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $admin->id,
            'type' => \App\Notifications\UserActivityNotification::class,
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
            'type' => \App\Notifications\UserActivityNotification::class,
        ]);

        $this->actingAs($admin)
            ->delete(route('dashboard.users.destroy', $admin))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_driver_badge_number_is_always_null(): void
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
            'badgeNumber' => null,
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
            'badgeNumber' => 'ENF-100',
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
        User::factory()->create(['role' => User::ROLE_DRIVER, 'fullName' => 'Zelda Driver', 'badgeNumber' => null]);
        User::factory()->create(['role' => User::ROLE_OFFICER, 'fullName' => 'Aaron Enforcer']);

        $this->actingAs($admin)
            ->get(route('dashboard.users', ['search' => 'Zelda']))
            ->assertOk()
            ->assertSee('Zelda Driver')
            ->assertDontSee('Aaron Enforcer');

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
}
