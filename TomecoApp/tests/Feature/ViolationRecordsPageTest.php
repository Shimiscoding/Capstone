<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViolationRecordsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_view_violation_records_page(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Violation::create([
            'motorist_name' => 'Juan Dela Cruz', 'plate_number' => 'ABC 1234',
            'violation_type' => 'Illegal parking', 'fine_amount' => 500,
            'status' => 'unpaid',
        ]);

        $this->actingAs($admin)->get(route('dashboard.violation-records'))
            ->assertOk()->assertSee('Violation Records')->assertSee('ABC 1234')->assertSee('Illegal parking');
    }

}
