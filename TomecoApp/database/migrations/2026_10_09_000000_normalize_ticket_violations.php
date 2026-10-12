<?php

use App\Services\ViolationCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('violations_type', function (Blueprint $table): void {
            $table->id();
            $table->string('catalog_key', 100)->unique();
            $table->string('ordinance_code', 60)->nullable();
            $table->string('section_number', 30)->nullable();
            $table->text('description');
            $table->string('form_group')->index();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['ordinance_code', 'section_number'], 'violation_section_unique');
        });

        // Rename in place: IDs, timestamps, encrypted fields, and officer links remain intact.
        Schema::rename('violations', 'Ticket_issued');
        Schema::table('Ticket_issued', function (Blueprint $table): void {
            $table->index('created_at', 'ticket_issued_date_index');
        });
        Schema::create('violation_issued', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_issued_id')->constrained('Ticket_issued')->cascadeOnDelete();
            $table->foreignId('violations_type_id')->constrained('violations_type')->restrictOnDelete();
            $table->text('description_snapshot');
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->unique(['ticket_issued_id', 'violations_type_id'], 'ticket_violation_unique');
        });

        DB::transaction(function (): void {
            DB::table('Ticket_issued')->orderBy('id')->chunkById(100, function ($tickets): void {
                foreach ($tickets as $ticket) {
                    foreach (ViolationCatalog::legacyLabels($ticket->violation_type) as $label) {
                        $key = 'legacy:'.hash('sha256', strtolower(trim($label)));
                        DB::table('violations_type')->insertOrIgnore([
                            'catalog_key' => $key, 'description' => $label,
                            'form_group' => ViolationCatalog::groupFor($label), 'active' => true,
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                        DB::table('violation_issued')->insertOrIgnore([
                            'ticket_issued_id' => $ticket->id,
                            'violations_type_id' => DB::table('violations_type')->where('catalog_key', $key)->value('id'),
                            'description_snapshot' => $label,
                            'created_at' => $ticket->created_at, 'updated_at' => $ticket->updated_at,
                        ]);
                    }
                }
            });
        });

        // Fine remains the assessed TOTAL for a ticket; historical multi-offense fines
        // cannot safely be allocated to individual sections.
        Schema::table('Ticket_issued', fn (Blueprint $table) => $table->dropColumn('violation_type'));
    }

    public function down(): void
    {
        Schema::table('Ticket_issued', fn (Blueprint $table) => $table->text('violation_type')->nullable());
        DB::table('Ticket_issued')->orderBy('id')->chunkById(100, function ($tickets): void {
            foreach ($tickets as $ticket) {
                $labels = DB::table('violation_issued as offenses')
                    ->join('violations_type as types', 'types.id', '=', 'offenses.violations_type_id')
                    ->where('offenses.ticket_issued_id', $ticket->id)->orderBy('offenses.id')
                    ->get(['offenses.description_snapshot', 'offenses.remarks', 'types.ordinance_code', 'types.section_number'])
                    ->map(function ($offense): string {
                        $reference = trim(($offense->ordinance_code ?? '').($offense->section_number !== null ? ' Sec. '.$offense->section_number : ''));

                        return ($reference !== '' ? $reference.' — ' : '').$offense->description_snapshot
                            .(filled($offense->remarks) ? ' ('.$offense->remarks.')' : '');
                    })->implode(', ');
                DB::table('Ticket_issued')->where('id', $ticket->id)->update(['violation_type' => $labels]);
            }
        });
        Schema::dropIfExists('violation_issued');
        Schema::dropIfExists('violations_type');
        Schema::table('Ticket_issued', fn (Blueprint $table) => $table->dropIndex('ticket_issued_date_index'));
        Schema::rename('Ticket_issued', 'violations');
    }
};
