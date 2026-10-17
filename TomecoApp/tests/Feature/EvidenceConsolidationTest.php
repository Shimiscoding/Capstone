<?php

namespace Tests\Feature;

use App\Models\Violation;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EvidenceConsolidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_preserves_single_multiple_and_distinct_legacy_photos(): void
    {
        $migration = require database_path('migrations/2026_10_15_000100_consolidate_ticket_evidence.php');
        $this->assertFalse(Schema::hasColumn('Ticket_issued', 'evidence_image'));
        $migration->down();
        $single = $this->ticket(null, 'data:image/jpeg;base64,bGVnYWN5');
        $multiple = $this->ticket(['photo-a', 'photo-b'], 'photo-a');
        $distinct = $this->ticket(['photo-a', 'photo-b'], 'photo-c');
        $empty = $this->ticket(null, null);
        $arrayOnly = $this->ticket(['photo-a'], null);
        $before = DB::table('Ticket_issued')->orderBy('id')->get()->keyBy('id');

        $migration->up();
        $migration->up();

        $this->assertFalse(Schema::hasColumn('Ticket_issued', 'evidence_image'));
        foreach ([
            $single => ['data:image/jpeg;base64,bGVnYWN5'],
            $multiple => ['photo-a', 'photo-b'],
            $distinct => ['photo-a', 'photo-b', 'photo-c'],
            $empty => [],
            $arrayOnly => ['photo-a'],
        ] as $id => $photos) {
            $ticket = Violation::findOrFail($id);
            $this->assertSame($photos, $ticket->evidence_photos);
            foreach ((array) $before[$id] as $column => $value) {
                if (! in_array($column, ['evidence_image', 'evidence_images'], true)) {
                    $this->assertSame($value, $ticket->getRawOriginal($column));
                }
            }
        }
        // Existing authoritative collections do not get needlessly re-encrypted.
        $this->assertSame($before[$multiple]->evidence_images, Violation::findOrFail($multiple)->getRawOriginal('evidence_images'));
        $this->assertSame($before[$arrayOnly]->evidence_images, Violation::findOrFail($arrayOnly)->getRawOriginal('evidence_images'));
        $this->assertNotSame(json_encode(['data:image/jpeg;base64,bGVnYWN5']), Violation::findOrFail($single)->getRawOriginal('evidence_images'));

        $migration->down();
        $this->assertTrue(Schema::hasColumn('Ticket_issued', 'evidence_image'));
        $this->assertSame('photo-a', Crypt::decryptString(DB::table('Ticket_issued')->where('id', $distinct)->value('evidence_image')));
        $this->assertSame(['photo-a', 'photo-b', 'photo-c'], Violation::findOrFail($distinct)->evidence_images);
        $migration->up();
        $this->assertFalse(Schema::hasColumn('Ticket_issued', 'evidence_image'));
    }

    public function test_unreadable_evidence_aborts_without_dropping_the_column_or_partial_backfill(): void
    {
        $migration = require database_path('migrations/2026_10_15_000100_consolidate_ticket_evidence.php');
        $migration->down();
        $valid = $this->ticket(null, 'old-photo');
        $invalid = $this->ticket(null, null);
        DB::table('Ticket_issued')->where('id', $invalid)->update(['evidence_image' => 'unreadable-ciphertext']);

        try {
            $migration->up();
            $this->fail('Unreadable evidence must stop the migration.');
        } catch (DecryptException $exception) {
            $this->assertTrue(Schema::hasColumn('Ticket_issued', 'evidence_image'));
            $this->assertNull(DB::table('Ticket_issued')->where('id', $valid)->value('evidence_images'));
            $this->assertSame('old-photo', Crypt::decryptString(DB::table('Ticket_issued')->where('id', $valid)->value('evidence_image')));
        }
    }

    private function ticket(?array $photos, ?string $legacy): int
    {
        $ticket = Violation::create([
            'first_name' => 'Test', 'last_name' => 'Driver', 'plate_number' => 'TEST',
            'fine_amount' => 500, 'status' => 'unpaid', 'evidence_images' => $photos,
        ]);
        DB::table('Ticket_issued')->where('id', $ticket->id)->update([
            'evidence_image' => $legacy === null ? null : Crypt::encryptString($legacy),
        ]);

        return $ticket->id;
    }
}
