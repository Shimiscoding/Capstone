<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('violations', function (Blueprint $table): void {
            $table->foreignId('enforcer_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->string('enforcer_name')->nullable()->after('enforcer_id');
        });

        DB::table('violations')->whereNotNull('user_id')->orderBy('id')->each(function (object $violation): void {
            $user = DB::table('users')->find($violation->user_id);
            $name = $user ? collect([$user->firstName, $user->middleName, $user->lastName, $user->nameExtension])->filter()->join(' ') : null;

            DB::table('violations')->where('id', $violation->id)->update([
                'enforcer_id' => $violation->user_id,
                'enforcer_name' => $name,
            ]);
        });

        Schema::table('violations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('violations', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        DB::table('violations')->whereNotNull('enforcer_id')->update([
            'user_id' => DB::raw('enforcer_id'),
        ]);

        Schema::table('violations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('enforcer_id');
            $table->dropColumn('enforcer_name');
        });
    }
};
