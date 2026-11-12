<?php

use App\Models\Violation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A targeted repair may have already added some of these fields.
        $missingColumns = array_values(array_filter([
            'license_expiration_date',
            'driver_address',
            'accident_involved',
            'driver_response',
            'submission_fingerprint',
        ], fn (string $column): bool => ! Schema::hasColumn('Ticket_issued', $column)));

        Schema::table('Ticket_issued', function (Blueprint $table) use ($missingColumns): void {
            $table->text('plate_number')->nullable()->change();
            foreach ($missingColumns as $column) {
                match ($column) {
                    'accident_involved' => $table->boolean($column)->nullable(),
                    'submission_fingerprint' => $table->string($column, 64)->nullable(),
                    default => $table->text($column)->nullable(),
                };
            }
        });

        DB::table('Ticket_issued')->select('id')->where('motorist_key', Violation::makeMotoristKey(null, null))
            ->chunkById(500, function ($tickets): void {
                foreach ($tickets as $ticket) {
                    DB::table('Ticket_issued')->where('id', $ticket->id)->update([
                        'motorist_key' => Violation::makeAnonymousMotoristKey((string) $ticket->id),
                    ]);
                }
            });
    }

    public function down(): void
    {
        throw new RuntimeException('This forward-only migration cannot be rolled back without discarding mobile ticket details.');
    }
};
