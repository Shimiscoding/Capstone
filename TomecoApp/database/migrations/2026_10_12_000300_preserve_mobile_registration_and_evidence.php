<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Ticket_issued', function (Blueprint $table): void {
            // No historical ciphertext is rewritten or inferred from driver identity.
            foreach (['registration_plate_number', 'vehicle_make', 'vehicle_series', 'vehicle_body_type', 'vehicle_year_model', 'registered_owner_name', 'registered_owner_address'] as $column) {
                $table->text($column)->nullable();
            }
            $table->longText('evidence_images')->nullable();
            $table->text('vehicle_type')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Keep the migration marked as applied; a silent no-op would allow an
        // unsafe rerun against columns that still exist.
        throw new RuntimeException('This forward-only migration cannot be rolled back without losing registration or evidence data.');
    }
};
