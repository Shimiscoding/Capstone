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
        Schema::rename('violation_issued', 'ticket_violation_items');
        Schema::create('violation_issued', function (Blueprint $table): void {
            $table->id();
            $table->string('label')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        foreach ([...ViolationCatalog::COMMON_GROUPS, 'Others'] as $position => $label) {
            DB::table('violation_issued')->insert([
                'label' => $label, 'sort_order' => $position, 'active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        Schema::table('ticket_violation_items', function (Blueprint $table): void {
            $table->index('ticket_issued_id', 'ticket_item_ticket_index');
            $table->dropUnique('ticket_violation_unique');
            $table->dropForeign(DB::connection()->getDriverName() === 'sqlite' ? ['violations_type_id'] : 'violation_issued_violations_type_id_foreign');
            $table->unsignedBigInteger('violation_issued_id')->nullable();
            $table->unsignedBigInteger('violations_type_id')->nullable()->change();
            $table->foreign('violations_type_id', 'ticket_item_type_foreign')->references('id')->on('violations_type')->restrictOnDelete();
        });
        $options = DB::table('violation_issued')->pluck('id', 'label');
        DB::table('ticket_violation_items')->orderBy('id')->chunkById(100, function ($items) use ($options): void {
            foreach ($items as $item) {
                $type = DB::table('violations_type')->find($item->violations_type_id);
                $label = $type->form_group;
                DB::table('ticket_violation_items')->where('id', $item->id)->update([
                    'violation_issued_id' => $options[$label] ?? $options['Others'],
                    'violations_type_id' => $type->id,
                ]);
            }
        });
        Schema::table('ticket_violation_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('violation_issued_id')->nullable(false)->change();
            $table->foreign('violation_issued_id', 'ticket_item_form_foreign')->references('id')->on('violation_issued')->restrictOnDelete();
            // A generated key also prevents duplicate form-only citations (NULL section).
            $table->unsignedBigInteger('section_key')->virtualAs('coalesce(violations_type_id, 0)');
            $table->unique(['ticket_issued_id', 'violation_issued_id', 'section_key'], 'ticket_form_section_unique');
        });
        Schema::table('violations_type', function (Blueprint $table): void {
            $table->dropIndex('violations_type_form_group_index');
            $table->dropColumn('form_group');
        });
    }

    public function down(): void
    {
        Schema::table('violations_type', fn (Blueprint $table) => $table->string('form_group')->nullable()->index());
        DB::table('violations_type')->orderBy('id')->each(function ($type): void {
            DB::table('violations_type')->where('id', $type->id)->update([
                'form_group' => ViolationCatalog::defaultGroupFor($type),
            ]);
        });
        DB::table('ticket_violation_items')->whereNull('violations_type_id')->orderBy('id')->each(function ($item): void {
            $key = 'legacy:'.hash('sha256', strtolower(trim($item->description_snapshot)));
            $label = DB::table('violation_issued')->where('id', $item->violation_issued_id)->value('label');
            DB::table('violations_type')->insertOrIgnore([
                'catalog_key' => $key, 'description' => $item->description_snapshot,
                'form_group' => $label, 'active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('ticket_violation_items')->where('id', $item->id)->update([
                'violations_type_id' => DB::table('violations_type')->where('catalog_key', $key)->value('id'),
            ]);
        });
        // The former layout cannot represent one section under several form options.
        // Stop before dropping those references rather than silently losing citations.
        $duplicate = DB::table('ticket_violation_items')->select('ticket_issued_id', 'violations_type_id')
            ->groupBy('ticket_issued_id', 'violations_type_id')->havingRaw('COUNT(*) > 1')->exists();
        if ($duplicate) {
            throw new RuntimeException('Rollback would lose form/section pairs. Preserve these tickets in the four-table schema.');
        }
        Schema::table('ticket_violation_items', function (Blueprint $table): void {
            $table->dropUnique('ticket_form_section_unique');
            $table->dropForeign(DB::connection()->getDriverName() === 'sqlite' ? ['violation_issued_id'] : 'ticket_item_form_foreign');
            $table->dropForeign(DB::connection()->getDriverName() === 'sqlite' ? ['violations_type_id'] : 'ticket_item_type_foreign');
            $table->dropColumn(['violation_issued_id', 'section_key']);
            $table->unsignedBigInteger('violations_type_id')->nullable(false)->change();
            $table->foreign('violations_type_id', 'violation_issued_violations_type_id_foreign')->references('id')->on('violations_type')->restrictOnDelete();
            $table->unique(['ticket_issued_id', 'violations_type_id'], 'ticket_violation_unique');
            $table->dropIndex('ticket_item_ticket_index');
        });
        Schema::drop('violation_issued');
        Schema::rename('ticket_violation_items', 'violation_issued');
    }
};
