<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This repair can be deployed independently to either supported ticket schema.
        $tables = array_values(array_filter(
            ['Ticket_issued', 'violations'],
            fn (string $table): bool => Schema::hasTable($table),
        ));

        if (count($tables) !== 1) {
            throw new RuntimeException('Expected exactly one ticket table (Ticket_issued or violations). Verify the server database and table name before applying this repair.');
        }

        $table = $tables[0];
        if (Schema::hasColumn($table, 'license_expiration_date')) {
            return;
        }

        Schema::table($table, function (Blueprint $table): void {
            // Text supports the existing encrypted license-field storage convention
            // as well as date strings. Historical dates remain unknown.
            $table->text('license_expiration_date')->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('This forward-only repair cannot be rolled back without losing recorded license expiration dates.');
    }
};
