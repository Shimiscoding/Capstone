<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->string('actor_name')->nullable()->after('user_id');
            $table->string('actor_role', 30)->nullable()->after('actor_name');
        });

        DB::table('audit_logs')->whereNotNull('user_id')->orderBy('id')->each(function (object $log): void {
            $user = DB::table('users')->find($log->user_id);
            if (! $user) {
                return;
            }

            DB::table('audit_logs')->where('id', $log->id)->update([
                'actor_name' => collect([$user->firstName, $user->middleName, $user->lastName, $user->nameExtension])->filter()->join(' '),
                'actor_role' => $user->role,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropColumn(['actor_name', 'actor_role']);
        });
    }
};
