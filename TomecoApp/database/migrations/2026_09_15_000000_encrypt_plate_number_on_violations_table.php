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
            $table->text('plate_number')->change();
        });

        DB::table('violations')->orderBy('id')->chunkById(100, function ($violations): void {
            foreach ($violations as $violation) {
                if ($violation->plate_number === null || $violation->plate_number === '') {
                    continue;
                }

                DB::table('violations')->where('id', $violation->id)->update([
                    'plate_number' => Crypt::encryptString((string) $violation->plate_number),
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('violations')->orderBy('id')->chunkById(100, function ($violations): void {
            foreach ($violations as $violation) {
                if ($violation->plate_number === null || $violation->plate_number === '') {
                    continue;
                }

                DB::table('violations')->where('id', $violation->id)->update([
                    'plate_number' => Crypt::decryptString((string) $violation->plate_number),
                ]);
            }
        });

        Schema::table('violations', function (Blueprint $table): void {
            $table->string('plate_number', 50)->change();
        });
    }
};
