<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_be_listed_and_shown(): void
    {
        $user = User::factory()->create(['middleName' => null, 'nameExtension' => null]);

        $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $user->id)
            ->assertJsonMissingPath('data.0.password');

        $this->getJson("/api/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonMissingPath('data.password');
    }

    public function test_a_user_can_be_created(): void
    {
        $response = $this->postJson('/api/users', [
            'firstName' => 'API',
            'lastName' => 'User',
            'plateNumber' => 'API-2001',
            'phoneNumber' => '09170000002',
            'email' => 'api-user@example.com',
            'role' => User::ROLE_ADMIN,
            'password' => 'password',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'User created successfully.')
            ->assertJsonPath('data.fullName', 'API User')
            ->assertJsonPath('data.role', User::ROLE_DRIVER)
            ->assertJsonMissingPath('data.password');

        $user = User::where('email', 'api-user@example.com')->firstOrFail();

        $this->assertSame(User::ROLE_DRIVER, $user->role);
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_a_user_can_be_updated_and_deleted(): void
    {
        $user = User::factory()->create(['middleName' => null, 'nameExtension' => null]);

        $this->putJson("/api/users/{$user->id}", [
            'firstName' => 'Updated',
            'lastName' => 'User',
            'phoneNumber' => '09170000003',
            'email' => 'updated-user@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'User updated successfully.')
            ->assertJsonPath('data.fullName', 'Updated User');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'phoneNumber' => '09170000003',
            'email' => 'updated-user@example.com',
        ]);

        $this->deleteJson("/api/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'User deleted successfully.');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
