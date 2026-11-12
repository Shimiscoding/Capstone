<?php

use App\Models\Violation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasIndex('Ticket_issued', 'violations_vehicle_type_index')) {
            Schema::table('Ticket_issued', fn (Blueprint $table) => $table->dropIndex('violations_vehicle_type_index'));
        }

        Schema::table('Ticket_issued', function (Blueprint $table): void {
            $table->text('license_type')->nullable()->change();
            $table->mediumText('vehicle_type')->nullable()->change();
        });

        if (! Schema::hasColumn('Ticket_issued', 'vehicle_type_key')) {
            Schema::table('Ticket_issued', fn (Blueprint $table) => $table->string('vehicle_type_key', 64)->nullable());
        }

        if (! Schema::hasIndex('Ticket_issued', ['vehicle_type_key'])) {
            Schema::table('Ticket_issued', fn (Blueprint $table) => $table->index('vehicle_type_key'));
        }

        DB::transaction(function (): void {
            DB::table('Ticket_issued')->select(['id', 'license_type', 'vehicle_type', 'vehicle_type_key'])
                ->lockForUpdate()->chunkById(200, function ($tickets): void {
                    foreach ($tickets as $ticket) {
                        $updates = [];
                        // The former 30-character license column could only hold plaintext.
                        if ($ticket->license_type !== null && ! Encrypter::appearsEncrypted($ticket->license_type)) {
                            $updates['license_type'] = Crypt::encryptString($ticket->license_type);
                        }
                        if ($ticket->vehicle_type !== null && $ticket->vehicle_type_key === null) {
                            $updates['vehicle_type'] = Crypt::encryptString($ticket->vehicle_type);
                            $updates['vehicle_type_key'] = Violation::makeVehicleTypeKey($ticket->vehicle_type);
                        }
                        if ($updates !== []) {
                            DB::table('Ticket_issued')->where('id', $ticket->id)->update($updates);
                        }
                    }
                });
        });
    }

    public function down(): void
    {
        throw new RuntimeException('This migration is forward-only to keep ticket types encrypted.');
    }
};
