<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_officer_can_log_in_to_the_mobile_api(): void
    {
        $officer = User::factory()->create([
            'role' => User::ROLE_OFFICER,
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'login' => $officer->email,
            'password' => 'password123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.role', User::ROLE_OFFICER)
            ->assertJsonStructure(['token']);
    }

    public function test_admin_cannot_log_in_to_the_mobile_api(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'password' => 'password123',
        ]);

        $this->postJson('/api/auth/login', [
            'login' => $admin->email,
            'password' => 'password123',
        ])->assertForbidden();
    }

    public function test_mobile_login_enforces_the_configured_limit_across_username_and_email(): void
    {
        app(Settings::class)->putMany(['security.login_attempts' => 3]);
        $officer = User::factory()->create([
            'role' => User::ROLE_OFFICER,
            'username' => 'officer.login',
            'password' => 'password123',
        ]);
        foreach ([$officer->username, $officer->email, $officer->username] as $login) {
            $this->postJson('/api/auth/login', ['login' => $login, 'password' => 'incorrect'])
                ->assertUnprocessable();
        }

        $credentials = ['login' => $officer->email, 'password' => 'password123'];
        $this->postJson('/api/auth/login', $credentials)->assertTooManyRequests()->assertHeader('Retry-After');
        $this->travel(61)->seconds();
        $this->postJson('/api/auth/login', $credentials)->assertOk();
    }

    public function test_mobile_bearer_tokens_expire_using_the_admin_setting(): void
    {
        app(Settings::class)->putMany(['security.api_token_days' => 1]);
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER]);
        $token = $officer->createToken(User::MOBILE_TOKEN_NAME)->plainTextToken;
        $other = $officer->createToken('other-client')->plainTextToken;

        $this->withToken($token)->getJson('/api/auth/profile')->assertOk();
        $this->travel(25)->hours();
        app('auth')->forgetGuards();
        $this->withToken($token)->getJson('/api/auth/profile')->assertUnauthorized();
        app('auth')->forgetGuards();
        $this->withToken($other)->getJson('/api/auth/profile')->assertOk();
    }
}
