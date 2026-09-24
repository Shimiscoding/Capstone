<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ViolationSignatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_submission_records_signature_and_enforcer(): void
    {
        $enforcer = User::factory()->create([
            'role' => User::ROLE_OFFICER,
            'signature' => 'data:image/png;base64,enforcer-signature',
        ]);
        Sanctum::actingAs($enforcer);

        $response = $this->postJson('/api/violations', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'plate_number' => 'ABC-1234',
            'vehicle_type' => 'motorcycle',
            'violation_type' => 'Illegal parking',
            'fine_amount' => 500,
            'evidence_image' => 'data:image/jpeg;base64,ZXZpZGVuY2U=',
            'signature' => 'data:image/png;base64,motorist-signature',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.enforcer_id', $enforcer->id)
            ->assertJsonPath('data.enforcer_name', $enforcer->fullName)
            ->assertJsonPath('data.enforcer_signature', 'data:image/png;base64,enforcer-signature')
            ->assertJsonPath('data.evidence_image', 'data:image/jpeg;base64,ZXZpZGVuY2U=')
            ->assertJsonPath('data.signature', 'data:image/png;base64,motorist-signature');

        $violation = Violation::firstOrFail();
        $this->assertTrue($violation->enforcer->is($enforcer));
        $this->assertSame('data:image/png;base64,motorist-signature', $violation->signature);
        $this->assertSame('data:image/png;base64,enforcer-signature', $violation->enforcer_signature);
        $this->assertSame('data:image/jpeg;base64,ZXZpZGVuY2U=', $violation->evidence_image);
        $this->assertNotSame($violation->signature, $violation->getRawOriginal('signature'));
    }

    public function test_phone_submission_requires_a_signature(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_OFFICER]));

        $this->postJson('/api/violations', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'plate_number' => 'ABC-1234',
            'vehicle_type' => 'Motorcycle',
            'violation_type' => 'Illegal parking',
            'fine_amount' => 500,
            'evidence_image' => 'data:image/jpeg;base64,ZXZpZGVuY2U=',
        ])->assertUnprocessable()->assertJsonValidationErrors('signature');
    }

    public function test_enforcer_can_save_and_retrieve_their_signature_from_phone(): void
    {
        $enforcer = User::factory()->create(['role' => User::ROLE_OFFICER]);
        Sanctum::actingAs($enforcer);

        $this->putJson('/api/auth/signature', [
            'signature' => 'data:image/png;base64,enforcer-signature',
        ])->assertOk()->assertJsonPath('data.signature', 'data:image/png;base64,enforcer-signature');

        $this->getJson('/api/auth/profile')
            ->assertOk()
            ->assertJsonPath('data.signature', 'data:image/png;base64,enforcer-signature');

        $this->assertNotSame(
            'data:image/png;base64,enforcer-signature',
            $enforcer->fresh()->getRawOriginal('signature')
        );
    }

    public function test_motorist_signature_is_shown_on_the_violation_details_page(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $enforcerSignature = 'data:image/png;base64,ZW5mb3JjZXI=';
        $enforcer = User::factory()->create([
            'role' => User::ROLE_OFFICER,
            'signature' => $enforcerSignature,
        ]);
        $signature = 'data:image/png;base64,iVBORw0KGgo=';
        $violation = Violation::create([
            'enforcer_id' => $enforcer->id,
            'enforcer_name' => $enforcer->fullName,
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'plate_number' => 'ABC-1234',
            'vehicle_type' => 'Motorcycle',
            'violation_type' => 'Illegal parking',
            'fine_amount' => 500,
            'evidence_image' => 'data:image/jpeg;base64,ZXZpZGVuY2U=',
            'signature' => $signature,
            'enforcer_signature' => $enforcerSignature,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard.violation-records.show', $violation))
            ->assertOk()
            ->assertSee('Driver signature')
            ->assertSee('Evidence image')
            ->assertSee('Enforcer signature')
            ->assertSee($signature, false)
            ->assertSee('Captured for ticket #0000001')
            ->assertSee($enforcer->fullName)
            ->assertSee('Issuing enforcer')
            ->assertSee($enforcerSignature, false);
    }
}
