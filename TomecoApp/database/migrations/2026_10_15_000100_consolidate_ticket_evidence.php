<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('Ticket_issued', 'evidence_image')) {
            return;
        }

        // Use raw rows: current model casts no longer include the retired column.
        // Finish and validate the complete backfill before the MySQL DDL commit.
        DB::transaction(function (): void {
            DB::table('Ticket_issued')->orderBy('id')->lockForUpdate()->chunkById(10, function ($tickets): void {
                foreach ($tickets as $ticket) {
                    $photos = $ticket->evidence_images === null ? []
                        : json_decode(Crypt::decryptString($ticket->evidence_images), true, flags: JSON_THROW_ON_ERROR);
                    if (! is_array($photos) || ! array_is_list($photos)
                        || count(array_filter($photos, fn ($photo) => is_string($photo) && $photo !== '')) !== count($photos)) {
                        throw new RuntimeException('Invalid evidence collection on ticket '.$ticket->id);
                    }

                    $legacy = $ticket->evidence_image === null ? null : Crypt::decryptString($ticket->evidence_image);
                    if (filled($legacy) && ! in_array($legacy, $photos, true)) {
                        // Keep the existing photo order and preserve a distinct legacy
                        // photo rather than silently discarding it during consolidation.
                        $photos[] = $legacy;
                        DB::table('Ticket_issued')->where('id', $ticket->id)->update([
                            'evidence_images' => Crypt::encryptString(json_encode($photos, JSON_THROW_ON_ERROR)),
                        ]);
                    }
                }
            });
        });

        Schema::table('Ticket_issued', fn (Blueprint $table) => $table->dropColumn('evidence_image'));
    }

    public function down(): void
    {
        if (! Schema::hasColumn('Ticket_issued', 'evidence_image')) {
            Schema::table('Ticket_issued', fn (Blueprint $table) => $table->longText('evidence_image')->nullable());
        }

        // Restore the old first-photo projection without discarding the collection.
        DB::transaction(function (): void {
            DB::table('Ticket_issued')->orderBy('id')->lockForUpdate()->chunkById(10, function ($tickets): void {
                foreach ($tickets as $ticket) {
                    $photos = $ticket->evidence_images === null ? []
                        : json_decode(Crypt::decryptString($ticket->evidence_images), true, flags: JSON_THROW_ON_ERROR);
                    DB::table('Ticket_issued')->where('id', $ticket->id)->update([
                        'evidence_image' => isset($photos[0]) ? Crypt::encryptString($photos[0]) : null,
                    ]);
                }
            });
        });
    }
};
