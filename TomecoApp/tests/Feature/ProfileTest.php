<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_account_role_can_open_its_own_profile(): void
    {
        foreach (User::ROLES as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get(route('profile.edit'))
                ->assertOk()
                ->assertSee('My Profile');
        }
    }

    public function test_user_can_update_own_profile_without_changing_role(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_SUPERVISOR,
            'username' => 'old.username',
            'address' => 'Old Address',
            'area' => 'Old Area',
            'barangay' => 'Old Barangay',
        ]);

        $this->actingAs($user)->put(route('profile.update'), [
            'firstName' => 'Updated',
            'middleName' => '',
            'lastName' => 'Profile',
            'nameExtension' => '',
            'username' => 'updated.profile',
            'address' => 'New Address',
            'area' => 'New Area',
            'barangay' => 'New Barangay',
            'phoneNumber' => $user->phoneNumber,
            'email' => $user->email,
            'role' => User::ROLE_ADMIN,
            'password' => '',
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame('Updated Profile', $user->fullName);
        $this->assertSame('updated.profile', $user->username);
        $this->assertSame(User::ROLE_SUPERVISOR, $user->role);
    }
}
