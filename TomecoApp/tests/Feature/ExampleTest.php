<?php

namespace Tests\Feature;

use App\Models\User;
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

        $response->assertOk();
    }

    public function test_users_can_register(): void
    {
        $this->post('/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'name' => 'New User',
            'email' => 'new@example.com',
        ]);
    }

    public function test_authenticated_users_can_view_dashboard(): void
    {
        $user = User::factory()->create([
            'name' => 'Dashboard User',
            'email' => 'dashboard@example.com',
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Welcome, Dashboard User')
            ->assertSee('dashboard@example.com');
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
}
