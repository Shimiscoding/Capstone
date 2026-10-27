<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Violation;
use Database\Seeders\CashierSampleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CashierDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_can_sign_in_reach_the_overview_and_sign_out(): void
    {
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER, 'password' => 'password']);

        $this->post(route('login.store'), ['login' => $cashier->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertRedirect(route('cashier.dashboard'));
        $this->get(route('cashier.dashboard'))->assertOk()->assertSee('Ticket overview');
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_cashier_overview_shows_real_decrypted_tickets_without_management_links(): void
    {
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $ticket = $this->ticket();
        $this->ticket(['plate_number' => 'PAID 123', 'status' => 'paid']);

        $this->actingAs($cashier)->get(route('cashier.dashboard'))
            ->assertOk()
            ->assertSee('#'.str_pad((string) $ticket->id, 7, '0', STR_PAD_LEFT))
            ->assertSee('Juan Dela Cruz')
            ->assertSee('ABC 1234')
            ->assertSee('Illegal parking')
            ->assertSee('500.00')
            ->assertSee('Paid')
            ->assertSee('Unpaid')
            ->assertSee('Issued (PHT)')
            ->assertDontSee(route('dashboard.users.create'))
            ->assertDontSee(route('dashboard.violation-records.show', $ticket))
            ->assertDontSee(route('dashboard.settings'))
            ->assertDontSee(route('profile.edit'))
            ->assertDontSee('Search dashboard');
    }

    public function test_empty_overview_explains_that_issued_tickets_will_appear(): void
    {
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);

        $this->actingAs($cashier)->get(route('cashier.dashboard'))
            ->assertOk()->assertSee('No tickets available')->assertSee('Showing 0');
    }

    public function test_overview_paginates_in_a_stable_newest_first_order(): void
    {
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $oldest = $this->ticket(['plate_number' => 'OLDEST-RECORD']);
        foreach (range(1, 15) as $number) {
            $this->ticket(['plate_number' => 'RECENT-'.$number]);
        }

        $this->actingAs($cashier)->get(route('cashier.dashboard'))
            ->assertOk()
            ->assertDontSee($oldest->plate_number)
            ->assertViewHas('tickets', fn ($tickets) => $tickets->count() === 15 && $tickets->total() === 16);

        $this->get(route('cashier.dashboard', ['page' => 2]))
            ->assertOk()->assertSee($oldest->plate_number)
            ->assertViewHas('tickets', fn ($tickets) => $tickets->count() === 1);

        $this->get(route('cashier.dashboard', ['page' => 999]))
            ->assertRedirect(route('cashier.dashboard', ['page' => 2]));
    }

    public function test_guests_and_other_roles_cannot_open_the_cashier_dashboard(): void
    {
        $this->get(route('cashier.dashboard'))->assertRedirect(route('login'));

        foreach ([User::ROLE_ADMIN, User::ROLE_SUPERVISOR, User::ROLE_OFFICER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('cashier.dashboard'))->assertForbidden();
        }
    }

    public function test_cashier_cannot_open_other_website_pages_or_mutate_accounts(): void
    {
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $ticket = $this->ticket();
        $this->actingAs($cashier);

        foreach ([
            'dashboard.analytics', 'dashboard.violation-records', 'dashboard.users.create',
            'dashboard.users.supervisors', 'dashboard.attendance', 'supervisor.team',
            'location-monitoring.index', 'area-assignments.index', 'dashboard.audit-logs',
            'dashboard.settings', 'profile.edit', 'dashboard.notifications.feed',
        ] as $route) {
            $this->get(route($route))->assertForbidden()->assertSee('Back to ticket overview');
        }

        $this->get(route('dashboard.violation-records.show', $ticket))->assertForbidden();
        $this->put(route('profile.update'), ['firstName' => 'Changed'])->assertForbidden();
        $this->post(route('dashboard.users.store'), [])->assertForbidden();
        $this->delete(route('dashboard.users.destroy', $cashier))->assertForbidden();
        $this->assertModelExists($cashier);
        $this->assertSame($cashier->firstName, $cashier->fresh()->firstName);
    }

    public function test_cashier_cannot_use_authenticated_api_endpoints_even_with_a_token(): void
    {
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        Sanctum::actingAs($cashier);

        $this->getJson('/api/users')->assertForbidden();
        $this->postJson('/api/violations', [])->assertForbidden();
        $this->postJson('/api/auth/profile-photo', ['remove_profile_photo' => true])->assertForbidden();
        $this->putJson('/api/auth/signature', [])->assertForbidden();
    }

    public function test_inactive_cashier_cannot_sign_in(): void
    {
        $cashier = User::factory()->create([
            'role' => User::ROLE_CASHIER, 'account_status' => User::STATUS_INACTIVE, 'password' => 'password',
        ]);

        $this->post(route('login.store'), ['login' => $cashier->email, 'password' => 'password'])
            ->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_sample_seeder_creates_a_cashier_and_preserves_existing_credentials(): void
    {
        $this->seed(CashierSampleSeeder::class);
        $cashier = User::where('email', 'cashier@example.com')->sole();
        $this->assertTrue($cashier->isCashier());
        $this->assertSame(User::STATUS_ACTIVE, $cashier->account_status);
        $this->assertTrue(Hash::isHashed($cashier->password));
        $passwordHash = $cashier->password;

        $this->seed(CashierSampleSeeder::class);
        $this->assertSame(1, User::where('email', 'cashier@example.com')->count());
        $this->assertSame($passwordHash, $cashier->fresh()->password);
    }

    public function test_sample_seeder_does_not_repurpose_an_existing_account(): void
    {
        $admin = User::factory()->create(['email' => 'cashier@example.com', 'role' => User::ROLE_ADMIN]);

        try {
            $this->seed(CashierSampleSeeder::class);
            $this->fail('Expected the conflicting account to be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('already used by another role', $exception->getMessage());
        }

        $this->assertTrue($admin->fresh()->isAdmin());
    }

    private function ticket(array $attributes = []): Violation
    {
        return Violation::create(array_merge([
            'first_name' => 'Juan', 'middle_name' => 'Dela', 'last_name' => 'Cruz',
            'plate_number' => 'ABC 1234', 'violation_type' => 'Illegal parking',
            'fine_amount' => 500, 'status' => 'unpaid',
        ], $attributes));
    }
}
