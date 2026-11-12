<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Settings;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_page_can_be_rendered(): void
    {
        $this->get(route('password.request'))->assertOk();
    }

    public function test_reset_link_can_be_requested(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_mobile_reset_uses_the_existing_broker_without_disclosing_accounts(): void
    {
        Notification::fake();
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $endpoint = '/api/auth/forgot-password';

        $accepted = $this->postJson($endpoint, ['email' => $officer->email])->assertOk()->assertJsonPath('success', true)->json();
        $this->postJson($endpoint, ['email' => 'unknown@example.test'])->assertOk()->assertExactJson($accepted);
        $this->postJson($endpoint, ['email' => $admin->email])->assertOk()->assertExactJson($accepted);
        $this->postJson($endpoint, ['email' => $officer->email])->assertOk()->assertExactJson($accepted);
        $this->postJson($endpoint, ['email' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson($endpoint, ['email' => $officer->email])->assertTooManyRequests();

        Notification::assertSentToTimes($officer, ResetPassword::class, 1);
        Notification::assertNotSentTo($admin, ResetPassword::class);
    }

    public function test_reset_page_preserves_link_details_and_configured_password_minimum(): void
    {
        app(Settings::class)->putMany(['security.password_min_length' => 12]);

        $this->get(route('password.reset', [
            'token' => 'preview-reset-token',
            'email' => 'preview@example.com',
        ]))
            ->assertOk()
            ->assertSee('name="token" value="preview-reset-token"', false)
            ->assertSee('value="preview@example.com"', false)
            ->assertSee('minlength="12"', false)
            ->assertSeeText('At least 12 characters.');
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        Notification::fake();
        $user = User::factory()->create(['role' => User::ROLE_OFFICER]);
        $mobileToken = $user->createToken(User::MOBILE_TOKEN_NAME)->accessToken;
        $otherToken = $user->createToken('other-client')->accessToken;

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])->assertRedirect(route('login'));

            return Hash::check('new-password', $user->fresh()->password);
        });

        $this->assertModelMissing($mobileToken);
        $this->assertModelExists($otherToken);
    }
}
