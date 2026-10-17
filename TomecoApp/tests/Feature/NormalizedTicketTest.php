<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationIssued;
use App\Models\ViolationType;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NormalizedTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_section_groups_preserve_the_full_meaning_of_the_supplied_descriptions(): void
    {
        $this->signInOfficer();
        foreach ([5 => 'Others', 20 => 'Driving without D/L', 21 => 'Others', 50 => 'Illegal parking',
            48 => 'Obstruction', 49 => 'Obstruction', 71 => 'Obstruction', 54 => 'Others', 70 => 'Others'] as $section => $label) {
            $this->assertSame($label, ViolationType::findOrFail($section)->formOption->label);
        }
        $this->postJson('/api/violations', $this->payload([
            ['violations_type_id' => 5], ['violations_type_id' => 20],
        ]))->assertCreated();
        $ticket = Violation::firstOrFail();
        $before = DB::table('ticket_violation_items')->where('ticket_issued_id', $ticket->id)->get()->toArray();
        $migration = require database_path('migrations/2026_10_15_000000_correct_ambiguous_violation_groups.php');
        $migration->down();
        $migration->up();
        $this->assertEquals($before, DB::table('ticket_violation_items')->where('ticket_issued_id', $ticket->id)->get()->toArray());
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->get(route('dashboard.analytics'))->assertOk()
            ->assertViewHas('violationTrends', fn ($rows) => $rows->firstWhere('violation_type', 'Others')->total == 1
                && $rows->firstWhere('violation_type', 'Driving without D/L')->total == 1);
    }

    public function test_corrected_groups_are_returned_by_the_api_and_old_pairs_are_rejected(): void
    {
        $this->signInOfficer();
        $license = ViolationIssued::where('label', 'Driving without D/L')->firstOrFail();
        $headlight = ViolationIssued::where('label', 'Defective Head Light')->firstOrFail();
        $others = ViolationIssued::where('label', 'Others')->firstOrFail();
        $this->getJson('/api/violation-types?form_group=Driving%20without%20D%2FL')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.section_id', 20);
        $this->getJson('/api/violation-types?form_group=Defective%20Head%20Light')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/violation-types?form_group=Others')
            ->assertOk()->assertJsonCount(61, 'data')->assertJsonPath('meta.total', 61);
        foreach ([5 => $headlight->id, 21 => $license->id] as $section => $oldGroup) {
            $this->postJson('/api/violations', $this->payload([
                ['violation_issued_id' => $oldGroup, 'violations_type_id' => $section],
            ]))->assertUnprocessable();
        }
        $this->assertDatabaseCount('Ticket_issued', 0);
        $this->postJson('/api/violations', $this->payload([
            ['violation_issued_id' => $others->id, 'violations_type_id' => 5],
            ['violation_issued_id' => $others->id, 'violations_type_id' => 21],
        ]))->assertCreated()->assertJsonCount(2, 'data.issued_violations');
    }

    public function test_group_correction_preserves_historical_citations_and_custom_catalog_entries(): void
    {
        $this->signInOfficer();
        $migration = require database_path('migrations/2026_10_15_000000_correct_ambiguous_violation_groups.php');
        $migration->down();
        $this->postJson('/api/violations', $this->payload([
            ['violations_type_id' => 5], ['violations_type_id' => 21],
        ]))->assertCreated();
        $ticketBefore = DB::table('Ticket_issued')->first();
        $itemsBefore = DB::table('ticket_violation_items')->orderBy('id')->get()->toArray();
        $typesBefore = DB::table('violations_type')->orderBy('section_id')->pluck('description', 'section_id')->all();
        $optionsBefore = DB::table('violation_issued')->orderBy('id')->get()->toArray();
        $migration->up();
        $migration->up(); // Re-running the correction is harmless.
        $this->assertEquals($ticketBefore, DB::table('Ticket_issued')->first());
        $this->assertEquals($itemsBefore, DB::table('ticket_violation_items')->orderBy('id')->get()->toArray());
        $this->assertSame($typesBefore, DB::table('violations_type')->orderBy('section_id')->pluck('description', 'section_id')->all());
        $this->assertEquals($optionsBefore, DB::table('violation_issued')->orderBy('id')->get()->toArray());
        $this->assertSame('Others', ViolationType::findOrFail(5)->formOption->label);
        $this->assertSame('Others', ViolationType::findOrFail(21)->formOption->label);

        $migration->down();
        $this->assertSame('Defective Head Light', ViolationType::findOrFail(5)->formOption->label);
        $this->assertSame('Driving without D/L', ViolationType::findOrFail(21)->formOption->label);
        ViolationType::findOrFail(5)->update(['description' => 'Locally maintained headlight-only entry']);
        $customGroup = ViolationIssued::where('label', 'Obstruction')->firstOrFail();
        ViolationType::findOrFail(21)->update(['violation_issued_id' => $customGroup->id]);
        $migration->up();
        $this->assertSame('Defective Head Light', ViolationType::findOrFail(5)->formOption->label);
        $this->assertSame($customGroup->id, ViolationType::findOrFail(21)->formOption->id);
    }

    private function payload(array $offenses): array
    {
        return [
            'first_name' => 'Juan', 'last_name' => 'Cruz', 'plate_number' => 'TEST-123',
            'vehicle_type' => 'Motorcycle', 'fine_amount' => 750,
            'evidence_image' => 'data:image/jpeg;base64,ZXZpZGVuY2U=',
            'signature' => 'data:image/png;base64,c2lnbmF0dXJl', 'violations' => $offenses,
        ];
    }

    private function signInOfficer(): User
    {
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER, 'signature' => 'data:image/png;base64,ZW5mb3JjZXI=']);
        Sanctum::actingAs($officer);

        return $officer;
    }

    public function test_multiple_sections_are_saved_on_one_ticket_and_counted_once_per_group(): void
    {
        $officer = $this->signInOfficer();
        $types = ViolationType::whereIn('section_id', [42, 43])->orderBy('section_id')->get();
        $response = $this->postJson('/api/violations', $this->payload($types->map(fn ($type) => ['violations_type_id' => $type->id])->all()));
        $response->assertCreated()->assertJsonCount(2, 'data.issued_violations');
        $ticket = Violation::firstOrFail();
        $this->assertSame(1, Violation::count());
        $this->assertCount(2, $ticket->issuedViolations);
        $this->assertSame(750.0, (float) $ticket->fine_amount);
        $this->assertSame($ticket->id, $officer->violations()->firstOrFail()->id);
        $this->assertFalse(Schema::hasColumn('Ticket_issued', 'violation_type'));

        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->get(route('dashboard.analytics'))
            ->assertOk()->assertViewHas('periodViolationTotal', 1)
            ->assertViewHas('violationTrends', fn ($rows) => $rows->count() === 1
                && $rows->first()->violation_type === 'Disregarding Traffic Sign'
                && (int) $rows->first()->total === 1)
            ->assertViewHas('otherViolationTickets', 0);
        $this->get(route('dashboard.violation-records.show', $ticket))
            ->assertOk()->assertSeeText('Sec. 42')->assertSeeText('Sec. 43');
        $this->get(route('dashboard.violation-records', ['violation_type' => 'Disregarding Traffic Sign']))
            ->assertOk()->assertViewHas('violations', fn ($rows) => $rows->total() === 1);
    }

    public function test_no_helmet_sections_share_one_group_and_groups_can_overlap(): void
    {
        $this->signInOfficer();
        $helmet = ViolationIssued::where('label', 'No helmet')->firstOrFail();
        $types = collect([101, 102])->map(fn ($section) => ViolationType::create([
            'section_id' => $section, 'description' => 'Helmet test '.$section, 'violation_issued_id' => $helmet->id,
        ]));
        $parking = ViolationType::findOrFail(7);
        $helmet = ViolationIssued::where('label', 'No helmet')->firstOrFail();
        $offenses = $types->map(fn ($type) => ['violation_issued_id' => $helmet->id, 'violations_type_id' => $type->id])->all();
        $offenses[] = ['violations_type_id' => $parking->id];
        $this->postJson('/api/violations', $this->payload($offenses))->assertCreated();
        $this->postJson('/api/violations', $this->payload([['violation_issued_id' => $helmet->id, 'violations_type_id' => $types->first()->id]]))->assertCreated();
        Violation::firstOrFail()->update(['status' => 'paid']);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->get(route('dashboard.analytics'))->assertOk()
            ->assertViewHas('periodViolationTotal', 2)
            ->assertViewHas('otherViolationTickets', 0)
            ->assertViewHas('violationTrends', function ($rows) {
                $helmet = $rows->firstWhere('violation_type', 'No helmet');

                return (int) $helmet->total === 2 && (int) $helmet->resolved === 1 && $rows->sum('total') === 3;
            });
    }

    public function test_invalid_duplicate_or_mismatched_sections_do_not_create_partial_tickets(): void
    {
        $this->signInOfficer();
        $type = ViolationType::findOrFail(7);
        $this->postJson('/api/violations', $this->payload([
            ['violations_type_id' => $type->id], ['violations_type_id' => $type->id],
        ]))->assertUnprocessable();
        $this->postJson('/api/violations', $this->payload([['violations_type_id' => 999999]]))->assertUnprocessable();
        $helmet = ViolationIssued::where('label', 'No helmet')->firstOrFail();
        $this->postJson('/api/violations', $this->payload([
            ['violation_issued_id' => $helmet->id, 'violations_type_id' => $type->id],
        ]))->assertUnprocessable();
        $this->postJson('/api/violations', $this->payload([]))->assertUnprocessable();
        $payload = $this->payload([['violations_type_id' => $type->id]]);
        $payload['violation_type'] = 'Illegal parking';
        $this->postJson('/api/violations', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('Ticket_issued', 0);
        $this->assertDatabaseCount('ticket_violation_items', 0);
    }

    public function test_catalog_is_authenticated_searchable_and_does_not_invent_helmet_sections(): void
    {
        $this->getJson('/api/violation-types')->assertUnauthorized();
        $this->signInOfficer();
        $this->getJson('/api/violation-types?search=43')->assertOk()
            ->assertJsonPath('data.0.section_id', 43);
        $this->getJson('/api/violation-form-options')->assertOk()->assertJsonCount(13, 'data');
        $this->getJson('/api/violation-types?form_group=No%20helmet')->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame(73, ViolationType::count());
        $this->assertSame(['section_id', 'description', 'violation_issued_id'], Schema::getColumnListing('violations_type'));
        foreach (require database_path('data/violation_sections.php') as $number => $description) {
            $this->assertSame($description, ViolationType::findOrFail($number)->description);
        }
    }

    public function test_cited_text_survives_catalog_edits_and_ticket_deletion_removes_only_issued_rows(): void
    {
        $this->signInOfficer();
        $type = ViolationType::findOrFail(7);
        $this->postJson('/api/violations', $this->payload([['violations_type_id' => $type->id, 'remarks' => 'At the corner']]))->assertCreated();
        $ticket = Violation::firstOrFail();
        $original = $ticket->violation_type;
        $type->update(['description' => 'Changed catalog description']);
        $this->assertSame($original, $ticket->fresh()->violation_type);
        $this->assertSame('At the corner', $ticket->issuedViolations->first()->remarks);
        $ticket->delete();
        $this->assertDatabaseCount('ticket_violation_items', 0);
        $this->assertDatabaseCount('violation_issued', 13);
        $this->assertDatabaseHas('violations_type', ['section_id' => $type->id]);
    }

    public function test_migration_preserves_ids_fines_ciphertext_and_splits_only_known_checkbox_lists(): void
    {
        // Exercise the migration with actual historical-format rows, not an empty schema.
        (require database_path('migrations/2026_10_09_000300_simplify_violation_sections.php'))->down();
        $fourTables = require database_path('migrations/2026_10_09_000200_separate_ticket_form_options.php');
        $fourTables->down();
        $migration = require database_path('migrations/2026_10_09_000000_normalize_ticket_violations.php');
        $migration->down();
        $ciphertext = encrypt('Juan', false);
        DB::table('violations')->insert([
            'id' => 101, 'first_name' => $ciphertext, 'plate_number' => encrypt('TEST', false),
            'violation_type' => 'Unregistered Vehicle, Illegal Parking, Disregarding Traffic Sign, No Helmet, Defective Headlight',
            'fine_amount' => 1234.50, 'status' => 'unpaid', 'created_at' => '2026-09-01 10:00:00',
        ]);
        DB::table('violations')->insert([
            'id' => 102, 'plate_number' => encrypt('OTHER', false),
            'violation_type' => 'Operating a vehicle loaded with soil, sand, gravel, stones and the likes without canvass covering.',
            'fine_amount' => 500, 'status' => 'paid',
        ]);
        $migration->up();
        $this->assertSame($ciphertext, DB::table('Ticket_issued')->where('id', 101)->value('first_name'));
        $this->assertSame(1234.5, (float) DB::table('Ticket_issued')->where('id', 101)->value('fine_amount'));
        $this->assertSame('2026-09-01 10:00:00', DB::table('Ticket_issued')->where('id', 101)->value('created_at'));
        $this->assertSame(5, DB::table('violation_issued')->where('ticket_issued_id', 101)->count());
        $this->assertSame(1, DB::table('violation_issued')->where('ticket_issued_id', 102)->count());
        $this->assertSame(0, DB::table('violations_type')->whereNotNull('section_number')->count());
        $migration->down();
        $this->assertSame(1234.5, (float) DB::table('violations')->where('id', 101)->value('fine_amount'));
        $this->assertStringContainsString('No Helmet', DB::table('violations')->where('id', 101)->value('violation_type'));
        $this->assertSame($ciphertext, DB::table('violations')->where('id', 101)->value('first_name'));
    }

    public function test_form_only_citations_and_explicit_section_pairs_use_separate_catalogs(): void
    {
        $this->signInOfficer();
        $helmet = ViolationIssued::where('label', 'No helmet')->firstOrFail();
        $sign = ViolationIssued::where('label', 'Disregarding Traffic Sign')->firstOrFail();
        $section = ViolationType::findOrFail(43);
        $this->postJson('/api/violations', $this->payload([
            ['violation_issued_id' => $helmet->id],
            ['violation_issued_id' => $sign->id, 'violations_type_id' => $section->id],
        ]))->assertCreated()->assertJsonPath('data.issued_violations.0.violation_issued_id', $helmet->id)
            ->assertJsonPath('data.issued_violations.0.violations_type_id', null)
            ->assertJsonPath('data.issued_violations.1.type.section_id', 43);
        $this->assertFalse(Schema::hasColumn('violation_issued', 'ticket_issued_id'));
        $this->assertFalse(Schema::hasColumn('violation_issued', 'active'));
        $this->assertFalse(Schema::hasColumn('violations_type', 'form_group'));
        $this->assertDatabaseCount('Ticket_issued', 1);
        $this->assertDatabaseCount('ticket_violation_items', 2);
        $this->assertDatabaseCount('violation_issued', 13);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->get(route('dashboard.violation-records.show', Violation::firstOrFail()))
            ->assertOk()->assertSeeText('No helmet')->assertDontSeeText('Section not recorded')->assertSeeText('Sec. 43');
    }

    public function test_form_only_duplicates_invalid_options_and_undescribed_others_are_rejected(): void
    {
        $this->signInOfficer();
        $helmet = ViolationIssued::where('label', 'No helmet')->firstOrFail();
        $others = ViolationIssued::where('label', 'Others')->firstOrFail();
        $this->postJson('/api/violations', $this->payload([
            ['violation_issued_id' => $helmet->id], ['violation_issued_id' => $helmet->id],
        ]))->assertUnprocessable();
        $this->postJson('/api/violations', $this->payload([['violation_issued_id' => 99999]]))->assertUnprocessable();
        $this->postJson('/api/violations', $this->payload([['violation_issued_id' => $others->id]]))->assertUnprocessable();
        $this->postJson('/api/violations', $this->payload([[]]))->assertUnprocessable();
        $this->assertDatabaseCount('Ticket_issued', 0);
        $this->assertDatabaseCount('ticket_violation_items', 0);
        $this->postJson('/api/violations', $this->payload([
            ['violation_issued_id' => $others->id, 'remarks' => 'Specific offense without a recorded section'],
        ]))->assertCreated();
    }

    public function test_database_prevents_duplicate_form_only_pairs(): void
    {
        $ticket = Violation::create(['first_name' => 'Test', 'last_name' => 'Driver', 'plate_number' => 'TEST',
            'violation_type' => 'No helmet', 'fine_amount' => 500]);
        $item = $ticket->issuedViolations->first();
        $this->expectException(QueryException::class);
        DB::table('ticket_violation_items')->insert([
            'ticket_issued_id' => $ticket->id, 'violation_issued_id' => $item->violation_issued_id,
            'violations_type_id' => null, 'description_snapshot' => 'No helmet',
        ]);
    }

    public function test_four_table_migration_preserves_existing_citation_rows_and_catalog_references(): void
    {
        $ticket = Violation::create(['first_name' => 'Test', 'last_name' => 'Driver', 'plate_number' => 'TEST',
            'violation_type' => 'Illegal parking, No helmet', 'fine_amount' => 1234.50]);
        (require database_path('migrations/2026_10_09_000300_simplify_violation_sections.php'))->down();
        $migration = require database_path('migrations/2026_10_09_000200_separate_ticket_form_options.php');
        $migration->down();
        $ticketBefore = (array) DB::table('Ticket_issued')->find($ticket->id);
        $itemsBefore = DB::table('violation_issued')->where('ticket_issued_id', $ticket->id)->orderBy('id')->get();
        $migration->up();
        $this->assertSame($ticketBefore, (array) DB::table('Ticket_issued')->find($ticket->id));
        foreach ($itemsBefore as $item) {
            $after = DB::table('ticket_violation_items')->find($item->id);
            $this->assertSame($item->ticket_issued_id, $after->ticket_issued_id);
            $this->assertSame($item->violations_type_id, $after->violations_type_id);
            $this->assertSame($item->description_snapshot, $after->description_snapshot);
            $this->assertSame($item->created_at, $after->created_at);
            $this->assertNotNull($after->violation_issued_id);
        }
        $this->assertSame(73, DB::table('violations_type')->whereNotNull('section_number')->count());
        $this->assertSame(13, DB::table('violation_issued')->count());
    }
}
