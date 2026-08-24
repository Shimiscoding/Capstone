<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\EmailVerificationOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailOtpVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_user_is_redirected_to_otp_page(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_user_can_verify_email_with_valid_otp(): void
    {
        $user = User::factory()->unverified()->create([
            'email_verification_otp' => Hash::make('123456'),
            'email_verification_otp_expires_at' => now()->addMinutes(10),
            'email_verification_otp_sent_at' => now(),
        ]);

        $this->actingAs($user)->post(route('verification.verify'), ['otp' => '123456'])
            ->assertRedirect(route('dashboard'));

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertNull($user->fresh()->email_verification_otp);
    }

    public function test_invalid_or_expired_otp_is_rejected(): void
    {
        $user = User::factory()->unverified()->create([
            'email_verification_otp' => Hash::make('123456'),
            'email_verification_otp_expires_at' => now()->subMinute(),
        ]);

        $this->actingAs($user)->post(route('verification.verify'), ['otp' => '123456'])
            ->assertSessionHasErrors('otp');

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_registration_sends_an_otp(): void
    {
        Notification::fake();

        $this->post(route('register.store'), [
            'firstName' => 'New',
            'lastName' => 'User',
            'phoneNumber' => '09179999999',
            'email' => 'otp@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'otp@example.com')->firstOrFail();
        Notification::assertSentTo($user, EmailVerificationOtp::class);
    }
}
