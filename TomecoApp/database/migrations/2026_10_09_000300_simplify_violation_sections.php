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
        $types = DB::table('violations_type')->get();
        $sections = $types->whereNotNull('section_number');
        if ($sections->pluck('section_number')->unique()->count() !== $sections->count()) {
            throw new RuntimeException('Section numbers overlap; the three-column catalog cannot represent these rows safely.');
        }
        $unknownIds = $types->whereNull('section_number')->pluck('id');
        if (DB::table('ticket_violation_items')->where(fn ($query) => $query->whereNull('violations_type_id')->orWhereIn('violations_type_id', $unknownIds))
            ->select('ticket_issued_id', 'violation_issued_id')->groupBy('ticket_issued_id', 'violation_issued_id')
            ->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Multiple unclassified citations share one form option. Preserve them before simplifying the section catalog.');
        }
        Schema::create('violation_sections_new', function (Blueprint $table): void {
            $table->unsignedBigInteger('section_id')->primary();
            $table->text('description');
            $table->foreignId('violation_issued_id')->constrained('violation_issued')->restrictOnDelete();
        });
        foreach ($sections as $type) {
            $option = ViolationCatalog::formOptionFor(ViolationCatalog::defaultGroupFor($type));
            DB::table('violation_sections_new')->insert([
                'section_id' => (int) $type->section_number, 'description' => $type->description,
                'violation_issued_id' => $option->id,
            ]);
        }
        Schema::table('ticket_violation_items', function (Blueprint $table): void {
            $table->dropForeign(DB::connection()->getDriverName() === 'sqlite' ? ['violations_type_id'] : 'ticket_item_type_foreign');
            $table->dropUnique('ticket_form_section_unique');
        });
        DB::transaction(function () use ($types): void {
            $map = $types->keyBy('id');
            DB::table('ticket_violation_items')->orderBy('id')->each(function ($item) use ($map): void {
                $section = $item->violations_type_id !== null ? $map[$item->violations_type_id]->section_number : null;
                DB::table('ticket_violation_items')->where('id', $item->id)->update([
                    'violations_type_id' => $section !== null ? (int) $section : null,
                ]);
            });
        });
        Schema::drop('violations_type');
        Schema::rename('violation_sections_new', 'violations_type');
        $this->itemConstraints('section_id');
    }

    public function down(): void
    {
        $sections = DB::table('violations_type')->get();
        Schema::create('violation_sections_old', function (Blueprint $table): void {
            $table->id();
            $table->string('catalog_key', 100)->unique();
            $table->string('ordinance_code', 60)->nullable();
            $table->string('section_number', 30)->nullable();
            $table->text('description');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['ordinance_code', 'section_number'], 'violation_section_unique');
        });
        foreach ($sections as $section) {
            DB::table('violation_sections_old')->insert([
                'id' => $section->section_id, 'catalog_key' => 'CO-2000-01:'.$section->section_id,
                'ordinance_code' => 'CO 2000-01', 'section_number' => (string) $section->section_id,
                'description' => $section->description, 'active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        Schema::table('ticket_violation_items', function (Blueprint $table): void {
            $table->dropForeign(DB::connection()->getDriverName() === 'sqlite' ? ['violations_type_id'] : 'ticket_item_type_foreign');
        });
        Schema::drop('violations_type');
        Schema::rename('violation_sections_old', 'violations_type');
        Schema::table('ticket_violation_items', fn (Blueprint $table) => $table->foreign('violations_type_id', 'ticket_item_type_foreign')
            ->references('id')->on('violations_type')->restrictOnDelete());
    }

    private function itemConstraints(string $key): void
    {
        Schema::table('ticket_violation_items', function (Blueprint $table) use ($key): void {
            $table->foreign('violations_type_id', 'ticket_item_type_foreign')->references($key)->on('violations_type')->restrictOnDelete();
            $table->unique(['ticket_issued_id', 'violation_issued_id', 'section_key'], 'ticket_form_section_unique');
        });
    }
};
