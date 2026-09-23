<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('violations', function (Blueprint $table): void {
            $table->text('first_name')->nullable()->after('id');
            $table->text('middle_name')->nullable()->after('first_name');
            $table->text('last_name')->nullable()->after('middle_name');
        });

        DB::table('violations')->orderBy('id')->chunkById(100, function ($violations): void {
            foreach ($violations as $violation) {
                $fullName = $this->decrypt($violation->motorist_name);
                $parts = preg_split('/\s+/', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $firstName = array_shift($parts) ?? '';
                $lastName = count($parts) > 0 ? array_pop($parts) : '';
                $middleName = implode(' ', $parts);

                DB::table('violations')->where('id', $violation->id)->update([
                    'first_name' => Crypt::encryptString($firstName),
                    'middle_name' => $middleName !== '' ? Crypt::encryptString($middleName) : null,
                    'last_name' => Crypt::encryptString($lastName),
                ]);
            }
        });

        Schema::table('violations', function (Blueprint $table): void {
            $table->dropColumn('motorist_name');
        });
    }

    public function down(): void
    {
        Schema::table('violations', function (Blueprint $table): void {
            $table->text('motorist_name')->nullable()->after('id');
        });

        DB::table('violations')->orderBy('id')->chunkById(100, function ($violations): void {
            foreach ($violations as $violation) {
                $fullName = collect(['first_name', 'middle_name', 'last_name'])
                    ->map(fn (string $column): string => $this->decrypt($violation->{$column}))
                    ->filter()
                    ->implode(' ');

                DB::table('violations')->where('id', $violation->id)->update([
                    'motorist_name' => Crypt::encryptString($fullName),
                ]);
            }
        });

        Schema::table('violations', function (Blueprint $table): void {
            $table->dropColumn(['first_name', 'middle_name', 'last_name']);
        });
    }

    private function decrypt(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            return $value;
        }
    }
};
