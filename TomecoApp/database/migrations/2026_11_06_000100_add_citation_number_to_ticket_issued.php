<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('Ticket_issued', 'citation_number')) {
            Schema::table('Ticket_issued', fn (Blueprint $table) => $table->string('citation_number', 16)->nullable());
        }

        if (! Schema::hasIndex('Ticket_issued', ['citation_number'], 'unique')) {
            Schema::table('Ticket_issued', fn (Blueprint $table) => $table->unique('citation_number'));
        }
    }

    public function down(): void
    {
        Schema::table('Ticket_issued', function (Blueprint $table): void {
            $table->dropUnique(['citation_number']);
            $table->dropColumn('citation_number');
        });
    }
};
