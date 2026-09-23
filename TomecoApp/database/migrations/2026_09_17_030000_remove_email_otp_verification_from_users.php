<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = collect([
                'email_verified_at',
                'email_verification_otp',
                'email_verification_otp_expires_at',
                'email_verification_otp_sent_at',
            ])->filter(fn (string $column): bool => Schema::hasColumn('users', $column))->all();

        if ($columns !== []) {
            Schema::table('users', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->string('email_verification_otp')->nullable()->after('email_verified_at');
            $table->timestamp('email_verification_otp_expires_at')->nullable()->after('email_verification_otp');
            $table->timestamp('email_verification_otp_sent_at')->nullable()->after('email_verification_otp_expires_at');
        });
    }
};
