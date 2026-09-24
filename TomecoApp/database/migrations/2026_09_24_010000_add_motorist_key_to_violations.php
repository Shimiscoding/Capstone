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
        Schema::table('violations', function (Blueprint $table): void {
            $table->string('motorist_key', 64)->nullable()->index()->after('enforcer_signature');
        });

        Violation::query()->orderBy('id')->each(function (Violation $violation): void {
            DB::table('violations')->where('id', $violation->id)->update([
                'motorist_key' => Violation::makeMotoristKey($violation->license_number, $violation->full_name),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('violations', function (Blueprint $table): void {
            $table->dropColumn('motorist_key');
        });
    }
};
