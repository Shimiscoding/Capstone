<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('firstName')->nullable()->after('id');
            $table->string('middleName')->nullable()->after('firstName');
            $table->string('lastName')->nullable()->after('middleName');
            $table->string('nameExtension', 20)->nullable()->after('lastName');
        });

        DB::table('users')->orderBy('id')->each(function (object $user): void {
            $parts = preg_split('/\s+/', trim((string) $user->fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $extension = null;

            if ($parts !== [] && preg_match('/^(jr\.?|sr\.?|ii|iii|iv)$/i', (string) end($parts))) {
                $extension = array_pop($parts);
            }

            $firstName = array_shift($parts) ?? '';
            $lastName = count($parts) > 0 ? array_pop($parts) : '';

            DB::table('users')->where('id', $user->id)->update([
                'firstName' => $firstName,
                'middleName' => $parts !== [] ? implode(' ', $parts) : null,
                'lastName' => $lastName,
                'nameExtension' => $extension,
            ]);
        });

        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('fullName'));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('fullName')->nullable()->after('id'));

        DB::table('users')->orderBy('id')->each(function (object $user): void {
            DB::table('users')->where('id', $user->id)->update([
                'fullName' => collect([$user->firstName, $user->middleName, $user->lastName, $user->nameExtension])->filter()->join(' '),
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['firstName', 'middleName', 'lastName', 'nameExtension']);
        });
    }
};
