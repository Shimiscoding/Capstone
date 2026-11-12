<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationType;
use App\Services\TicketEvidence;
use App\Support\SignatureImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class MobileContractTest extends TestCase
{
    use RefreshDatabase;

    private const VECTOR = 'SIG_VECTOR_V1|1000x360|10.0,20.0;100.0,40.0;_;50.0,60.0;120.0,70.0';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function payload(): array
    {
        return [
            'enforcer_id' => 99999, 'user_id' => 99999, 'enforcer_name' => 'Spoofed officer',
            'first_name' => 'Fictional', 'middle_name' => '', 'last_name' => 'Driver',
            'driver_name' => 'Fictional Driver', 'license_number' => '', 'license_type' => 'non_professional',
            'plate_number' => 'OBS-123', 'registration_plate_number' => 'DOC-456',
            'vehicle_type' => str_repeat('Long vehicle description ', 12),
            'vehicle_make' => 'Toyota', 'vehicle_series' => 'Hilux 2.4 G', 'vehicle_body_type' => 'Pickup',
            'vehicle_year_model' => '2024', 'registered_owner_name' => 'Separate Registered Owner',
            'registered_owner_address' => '123 Example Road, Tacloban City',
            'or_number' => 'OR-123', 'cr_number' => 'CR-456', 'violation_type' => 'Illegal parking',
            'fine_amount' => '500.00', 'status' => 'UNPAID', 'location' => 'Example street',
            'signature' => self::VECTOR,
        ];
    }

    public function test_android_multipart_preserves_registration_order_and_authenticated_snapshot(): void
    {
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER, 'signature' => self::VECTOR]);
        Sanctum::actingAs($officer);
        $photos = array_map(fn ($n) => UploadedFile::fake()->image("TMC-BB10F3D83CBA-evidence-$n.jpg", 20 + $n, 20), range(1, 5));
        $response = $this->post('/api/violations', $this->payload() + ['evidence_images' => $photos], ['Accept' => 'application/json']);
        $response->assertCreated()->assertJsonPath('data.enforcer_id', $officer->id)
            ->assertJsonPath('data.enforcer_signature', self::VECTOR)->assertJsonCount(5, 'data.evidence_images')
            ->assertJsonMissingPath('data.evidence_image')->assertJsonPath('data.citation_number', 'TMC-BB10F3D83CBA');
        $ticket = Violation::firstOrFail();
        foreach (['or_number', 'cr_number', 'registration_plate_number', 'vehicle_make', 'vehicle_series', 'vehicle_body_type', 'vehicle_year_model', 'registered_owner_name', 'registered_owner_address'] as $field) {
            $this->assertSame($this->payload()[$field], $ticket->$field);
            $this->assertNotSame($ticket->$field, $ticket->getRawOriginal($field));
        }
        foreach ($photos as $index => $photo) {
            $this->assertSame(file_get_contents($photo->getRealPath()), TicketEvidence::read($ticket->evidence_photos[$index]));
            $this->get(route('tickets.evidence', [$ticket, $index]))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        }
        $this->assertNotSame(json_encode($ticket->evidence_images), $ticket->getRawOriginal('evidence_images'));
        $this->assertCount(5, Storage::disk('local')->allFiles('ticket-evidence'));
        $this->assertSame('Fictional Driver', $ticket->full_name);
        $officer->update(['signature' => 'data:image/png;base64,Y2hhbmdlZA==']);
        $this->assertSame(self::VECTOR, $ticket->fresh()->enforcer_signature);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $html = $this->actingAs($admin)->get(route('dashboard.violation-records.show', $ticket));
        $html->assertOk()->assertSee('Observed plate')->assertSee('Document plate')->assertSee('Separate Registered Owner')
            ->assertSee('Hilux 2.4 G')->assertSee('Evidence photo 5 of 5')->assertSee(SignatureImage::source(self::VECTOR), false);
        $html->assertDontSee('data:image/png;base64,Y2hhbmdlZA==', false)->assertSee('TMC-BB10F3D83CBA');
        $this->get(route('dashboard.violation-records', ['search' => 'tmc-bb10f3d83cba', 'period' => 'all']))
            ->assertOk()->assertViewHas('violations', fn ($rows) => $rows->count() === 1)
            ->assertSee('TMC-BB10F3D83CBA');
        $this->getJson(route('dashboard.search', ['q' => 'TMC-BB10F3D83CBA']))
            ->assertOk()->assertJsonFragment(['label' => 'Ticket TMC-BB10F3D83CBA']);
    }

    public function test_single_file_and_missing_documents_remain_compatible(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_OFFICER, 'signature' => self::VECTOR]));
        $payload = $this->payload() + ['citation_number' => 'TMC-111122223333'];
        foreach (['or_number', 'cr_number', 'registration_plate_number', 'registered_owner_name', 'registered_owner_address'] as $field) {
            $payload[$field] = '';
        }
        $this->post('/api/violations', $payload + ['evidence_image' => UploadedFile::fake()->image('single.png')], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.citation_number', 'TMC-111122223333');
        $ticket = Violation::firstOrFail();
        $this->assertCount(1, $ticket->evidence_photos);
        $this->assertNull($ticket->registered_owner_name);
        $this->assertNull($ticket->registration_plate_number);
        $this->assertNull($ticket->or_number);
        $this->post('/api/violations', $payload + ['evidence_image' => UploadedFile::fake()->image('duplicate.png')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('citation_number');
        $this->assertDatabaseCount('Ticket_issued', 1);
    }

    public function test_invalid_evidence_count_type_and_size_are_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_OFFICER, 'signature' => self::VECTOR]));
        foreach ([[], array_fill(0, 6, UploadedFile::fake()->image('six.jpg')), [UploadedFile::fake()->create('file.txt', 1, 'text/plain')], [UploadedFile::fake()->image('large.jpg')->size(8193)]] as $photos) {
            $this->post('/api/violations', $this->payload() + ['evidence_images' => $photos], ['Accept' => 'application/json'])->assertUnprocessable();
        }
        $this->assertDatabaseCount('Ticket_issued', 0);
        $this->post('/api/violations', $this->payload() + ['evidence_images' => [UploadedFile::fake()->image('limit.jpg')->size(8192)]], ['Accept' => 'application/json'])->assertCreated();
    }

    public function test_identical_multipart_retry_returns_original_ticket_and_preserves_details_in_history(): void
    {
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER, 'signature' => self::VECTOR]);
        Sanctum::actingAs($officer);
        $type = ViolationType::findOrFail(7);
        $number = 'TMC-ABC123DEF456';
        $headers = ['Accept' => 'application/json', 'Idempotency-Key' => $number];
        $payload = array_diff_key($this->payload(), ['violation_type' => true]) + [
            'citation_number' => $number,
            'violations' => [['violation_issued_id' => (string) $type->violation_issued_id, 'violations_type_id' => '7']],
            'license_expiration_date' => '2031-04-20',
            'driver_address' => '5 Fictional Avenue',
            'accident_involved' => '0',
            'driver_response' => 'under_protest',
            'evidence_photo_count' => '1',
        ];
        $post = fn (array $fields, int $width = 20) => $this->post('/api/violations', $fields + [
            'evidence_images' => [UploadedFile::fake()->image('evidence.jpg', $width, 20)],
        ], $headers);

        $issued = $post($payload)->assertCreated()->assertJsonPath('data.citation_number', $number)
            ->assertJsonPath('data.enforcer_id', $officer->id)->assertJsonPath('data.status', 'unpaid')
            ->assertJsonPath('data.evidence_photo_count', 1)->assertJsonMissingPath('data.submission_fingerprint');
        $id = $issued->json('data.id');
        $officer->update(['signature' => null]);
        $post($payload)->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.enforcer_signature', self::VECTOR);
        $post(array_replace($payload, ['driver_address' => 'Changed address']))->assertUnprocessable()->assertJsonValidationErrors('citation_number');
        $post($payload, 21)->assertUnprocessable()->assertJsonValidationErrors('citation_number');
        $this->assertDatabaseCount('Ticket_issued', 1);
        $this->assertDatabaseCount('ticket_violation_items', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles('ticket-evidence'));
        $ticket = Violation::findOrFail($id);
        foreach (['driver_address', 'driver_response', 'license_expiration_date'] as $field) {
            $this->assertNotSame($payload[$field], $ticket->getRawOriginal($field));
        }

        $this->getJson('/api/violations')->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.current_page', 1)->assertJsonPath('meta.last_page', 1)
            ->assertJsonPath('data.0.citation_number', $number)->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.created_at', $issued->json('data.created_at'))
            ->assertJsonPath('data.0.license_expiration_date', '2031-04-20')->assertJsonPath('data.0.driver_address', '5 Fictional Avenue')
            ->assertJsonPath('data.0.accident_involved', false)->assertJsonPath('data.0.driver_response', 'under_protest')
            ->assertJsonPath('data.0.issued_violations.0.violations_type_id', 7)
            ->assertJsonPath('data.0.evidence_photo_count', 1)->assertJsonMissingPath('data.0.evidence_images');

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_OFFICER]));
        $post($payload)->assertUnprocessable()->assertJsonValidationErrors('citation_number');
        $this->getJson('/api/violations')->assertOk()->assertJsonPath('meta.total', 0)->assertJsonCount(0, 'data');
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->getJson('/api/violations')->assertForbidden();
    }

    public function test_bad_date_signature_or_missing_multipart_evidence_never_creates_a_ticket(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_OFFICER]));
        $number = 'TMC-001122334455';
        $headers = ['Accept' => 'application/json', 'Idempotency-Key' => $number];
        $payload = $this->payload() + ['citation_number' => $number, 'evidence_photo_count' => '1'];
        $this->post('/api/violations', $payload + ['license_expiration_date' => '2031-02-30'], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('license_expiration_date');
        $this->post('/api/violations', $payload, $headers)->assertUnprocessable()
            ->assertJsonValidationErrors('evidence_images')->assertJsonPath('message', 'One or more evidence photos did not reach the server. Check the attachments and retry.');
        $this->post('/api/violations', array_replace($payload, ['evidence_photo_count' => '0', 'signature' => 'SIG_VECTOR_V1|0x0|1,2']), $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('signature');
        $this->assertDatabaseCount('Ticket_issued', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles('ticket-evidence'));
    }

    public function test_missing_details_stay_null_and_unidentified_tickets_are_grouped_separately(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_OFFICER]));
        $this->postJson('/api/violations', ['violations' => [['violations_type_id' => 7]], 'fine_amount' => 500])
            ->assertCreated()->assertJsonPath('data.evidence_photo_count', 0)->assertJsonPath('data.plate_number', null)
            ->assertJsonPath('data.first_name', null)->assertJsonPath('data.accident_involved', null);
        $this->postJson('/api/violations', ['violations' => [['violations_type_id' => 7]], 'fine_amount' => 500])
            ->assertCreated();
        [$first, $second] = Violation::orderBy('id')->get();
        $originalKey = $first->motorist_key;
        $this->assertNotSame($originalKey, $second->motorist_key);
        $first->update(['status' => 'paid']);
        $this->assertSame($originalKey, $first->fresh()->motorist_key);
        $this->getJson('/api/violations')->assertOk()->assertJsonPath('data.1.status', 'paid');
    }

    public function test_partial_evidence_storage_failure_cleans_up_and_can_be_retried(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_OFFICER]));
        $disk = Storage::disk('local');
        $failingDisk = Mockery::mock();
        $failingDisk->shouldReceive('put')->once()->andReturnUsing($disk->put(...));
        $failingDisk->shouldReceive('put')->once()->andReturn(false);
        $failingDisk->shouldReceive('delete')->andReturnUsing($disk->delete(...));
        Storage::set('local', $failingDisk);

        $number = 'TMC-FFEEDDCCBBAA';
        $post = fn () => $this->post('/api/violations', $this->payload() + [
            'citation_number' => $number,
            'evidence_photo_count' => '2',
            'evidence_images' => [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.jpg')],
        ], ['Accept' => 'application/json', 'Idempotency-Key' => $number]);
        $post()->assertStatus(503)->assertJsonPath('message', 'An evidence photo could not be saved. Please retry the citation.');
        $this->assertDatabaseCount('Ticket_issued', 0);
        $this->assertCount(0, $disk->allFiles('ticket-evidence'));

        Storage::set('local', $disk);
        $post()->assertCreated()->assertJsonPath('data.evidence_photo_count', 2);
        $this->assertDatabaseCount('Ticket_issued', 1);
        $this->assertCount(2, $disk->allFiles('ticket-evidence'));
    }

    public function test_bearer_login_profile_and_logout_revoke_only_current_token(): void
    {
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER, 'password' => 'password123', 'username' => 'mobile.officer', 'signature' => self::VECTOR]);
        $other = $officer->createToken('other-client')->plainTextToken;
        $token = $this->postJson('/api/auth/login', ['login' => 'mobile.officer', 'password' => 'password123'])->assertOk()->json('token');
        $this->withToken($token)->getJson('/api/auth/profile')->assertOk()->assertJsonPath('data.username', 'mobile.officer');
        $this->withToken($token)->postJson('/api/auth/logout')->assertOk()->assertJsonStructure(['message']);
        // Laravel retains the guard in an in-process test; reset to simulate a new HTTP request.
        app('auth')->forgetGuards();
        $this->withToken($token)->getJson('/api/auth/profile')->assertUnauthorized();
        app('auth')->forgetGuards();
        $this->withToken($other)->getJson('/api/auth/profile')->assertOk();
    }

    public function test_disabled_accounts_and_wrong_roles_cannot_use_officer_endpoints(): void
    {
        foreach ([User::STATUS_INACTIVE, User::STATUS_SUSPENDED, User::STATUS_BANNED] as $status) {
            $officer = User::factory()->create(['role' => User::ROLE_OFFICER, 'password' => 'password123', 'account_status' => $status]);
            $this->postJson('/api/auth/login', ['login' => $officer->email, 'password' => 'password123'])->assertForbidden();
            Sanctum::actingAs($officer);
            $this->getJson('/api/auth/profile')->assertForbidden();
        }
        foreach ([User::ROLE_ADMIN, User::ROLE_SUPERVISOR] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));
            $this->postJson('/api/violations', $this->payload())->assertForbidden();
        }
    }

    public function test_user_management_requires_admin_and_supervisor_reads_only_own_team(): void
    {
        $this->getJson('/api/users')->assertUnauthorized();
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER, 'supervisor_id' => $supervisor->id]);
        $outsider = User::factory()->create(['role' => User::ROLE_OFFICER]);
        Sanctum::actingAs($officer);
        $this->getJson('/api/users')->assertForbidden();
        $this->putJson("/api/users/{$officer->id}", ['role' => User::ROLE_ADMIN])->assertForbidden();
        $this->deleteJson("/api/users/{$outsider->id}")->assertForbidden();
        Sanctum::actingAs($supervisor);
        $this->getJson('/api/users')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $officer->id);
        $this->getJson("/api/users/{$outsider->id}")->assertForbidden();
        $this->postJson('/api/users', [])->assertForbidden();
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->postJson('/api/users', ['firstName' => 'New', 'lastName' => 'Officer', 'username' => 'new.officer', 'email' => 'new@example.test', 'phoneNumber' => '09171234567', 'password' => 'password123', 'role' => User::ROLE_OFFICER, 'supervisor_id' => $supervisor->id])
            ->assertCreated()->assertJsonPath('data.supervisor_id', $supervisor->id)->assertJsonMissingPath('data.password');
    }

    public function test_signature_renderer_supports_images_and_rejects_unsafe_vectors(): void
    {
        $this->assertStringStartsWith('data:image/svg+xml;base64,', SignatureImage::source(self::VECTOR));
        $image = 'data:image/png;base64,aW1hZ2U=';
        $this->assertSame($image, SignatureImage::source($image));
        $this->assertSame($image, SignatureImage::source('aW1hZ2U='));
        foreach (['SIG_VECTOR_V1|0x360|1,2', 'SIG_VECTOR_V1|100x100|<script>,2', 'SIG_VECTOR_V1|100x100|Infinity,2', 'SIG_VECTOR_V1|100x100|9999,2'] as $invalid) {
            $this->assertNull(SignatureImage::source($invalid));
        }
    }

    public function test_private_evidence_cannot_be_referenced_by_a_different_officer(): void
    {
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER, 'signature' => self::VECTOR]);
        Sanctum::actingAs($officer);
        $this->post('/api/violations', $this->payload() + ['evidence_image' => UploadedFile::fake()->image('private.jpg')], ['Accept' => 'application/json'])->assertCreated();
        $ticket = Violation::firstOrFail();
        $reference = $ticket->evidence_photos[0];
        $this->assertStringNotContainsString('JFIF', Storage::disk('local')->get('ticket-evidence/'.substr($reference, strlen(TicketEvidence::PREFIX)).'.encrypted'));
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_OFFICER, 'signature' => self::VECTOR]));
        $this->getJson(route('tickets.evidence', [$ticket, 0]))->assertForbidden();
        $this->postJson('/api/violations', $this->payload() + ['evidence_image' => $reference])->assertUnprocessable()->assertJsonValidationErrors('evidence_image');
        Sanctum::actingAs($officer);
        $this->getJson(route('tickets.evidence', [$ticket, 5]))->assertNotFound();
    }

    public function test_mobile_schema_rollback_is_explicitly_blocked_to_preserve_data(): void
    {
        $migration = require database_path('migrations/2026_10_12_000300_preserve_mobile_registration_and_evidence.php');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('forward-only migration cannot be rolled back');
        $migration->down();
    }
}
