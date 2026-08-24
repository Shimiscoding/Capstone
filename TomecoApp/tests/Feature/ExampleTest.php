<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guests_are_sent_to_login_from_home(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    public function test_login_page_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
    }

    public function test_register_page_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertOk()
            ->assertSee('auth-page login-page', false)
            ->assertSee('TOMECO official seal')
            ->assertSee('Traffic Operation Management Enforcement and Control Office Tacloban')
            ->assertDontSee('TomecoApp');
    }

    public function test_admins_can_register(): void
    {
        $this->post('/register', [
            'firstName' => 'New',
            'middleName' => null,
            'lastName' => 'User',
            'nameExtension' => null,
            'phoneNumber' => '09170000001',
            'email' => 'new@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('verification.notice'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'firstName' => 'New',
            'lastName' => 'User',
            'phoneNumber' => '09170000001',
            'email' => 'new@example.com',
            'role' => User::ROLE_ADMIN,
        ]);
    }

    public function test_registration_cannot_override_the_admin_role(): void
    {
        $this->post('/register', [
            'firstName' => 'Role',
            'lastName' => 'Override',
            'phoneNumber' => '09170000002',
            'email' => 'role@example.com',
            'role' => User::ROLE_OFFICER,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('verification.notice'));

        $this->assertDatabaseHas('users', [
            'email' => 'role@example.com',
            'role' => User::ROLE_ADMIN,
        ]);
    }

    public function test_authenticated_users_can_view_dashboard(): void
    {
        $user = User::factory()->create([
            'fullName' => 'Dashboard User',
            'email' => 'dashboard@example.com',
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Welcome, Dashboard User')
            ->assertSee('dashboard@example.com');
    }

    public function test_dashboard_shows_ticket_context(): void
    {
        $user = User::factory()->create();
        Violation::create([
            'motorist_name' => 'Juan Dela Cruz',
            'plate_number' => 'ABC 123',
            'violation_type' => 'Illegal parking',
            'fine_amount' => 500,
        ]);
        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Tickets issued')
            ->assertSee('Illegal parking')
            ->assertSee('ABC 123');
    }

    public function test_users_can_log_in_and_log_out(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_user_can_log_in_again_after_logging_out(): void
    {
        $user = User::factory()->create([
            'password' => 'password',
        ]);

        $this->post('/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->post('/logout')->assertRedirect(route('login'));

        $loginPage = $this->get('/login')->assertOk();
        $this->assertStringContainsString('no-store', (string) $loginPage->headers->get('Cache-Control'));

        $this->post('/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_users_can_log_in_with_their_username(): void
    {
        $user = User::factory()->create([
            'username' => 'tomeco.admin',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'login' => 'tomeco.admin',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }
}
