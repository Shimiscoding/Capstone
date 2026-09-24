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
            $table->longText('evidence_image')->nullable()->change();
            $table->longText('enforcer_signature')->nullable()->after('signature');
        });

        DB::table('violations')->whereNotNull('evidence_image')->orderBy('id')->each(function (object $violation): void {
            DB::table('violations')->where('id', $violation->id)->update([
                'evidence_image' => Crypt::encryptString($violation->evidence_image),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('violations', function (Blueprint $table): void {
            $table->dropColumn('enforcer_signature');
        });
    }
};
