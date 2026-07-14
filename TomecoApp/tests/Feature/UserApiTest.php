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
        $user = User::factory()->create();

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
            'fullName' => 'API User',
            'badgeNumber' => 'BDG-200001',
            'phoneNumber' => '09170000002',
            'email' => 'api-user@example.com',
            'password' => 'password',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'User created successfully.')
            ->assertJsonPath('data.fullName', 'API User')
            ->assertJsonMissingPath('data.password');

        $user = User::where('email', 'api-user@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_a_user_can_be_updated_and_deleted(): void
    {
        $user = User::factory()->create();

        $this->putJson("/api/users/{$user->id}", [
            'fullName' => 'Updated User',
            'badgeNumber' => 'BDG-200002',
            'phoneNumber' => '09170000003',
            'email' => 'updated-user@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'User updated successfully.')
            ->assertJsonPath('data.fullName', 'Updated User');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'badgeNumber' => 'BDG-200002',
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
