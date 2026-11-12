<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\AttendanceRules;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class MockDefenseSeeder extends Seeder
{
    public function run(): void
    {
        $demoDatabase = realpath(storage_path('demo.sqlite'));

        if (! app()->environment('mock-defense') || config('database.default') !== 'sqlite' || ! $demoDatabase
            || realpath(DB::connection()->getDatabaseName()) !== $demoDatabase) {
            throw new LogicException('The mock defense accounts require the isolated demo SQLite database.');
        }

        $supervisor = User::firstOrCreate(['username' => 'demo.supervisor'], [
            'fullName' => 'Morgan Demo Supervisor',
            'email' => 'demo.supervisor@example.test',
            'phoneNumber' => '09000000003',
            'role' => User::ROLE_SUPERVISOR,
            'account_status' => User::STATUS_ACTIVE,
            'password' => 'TomecoDemo!2026',
        ]);

        $officer = User::firstOrCreate(['username' => 'demo.officer'], [
            'fullName' => 'Alex Demo Officer',
            'email' => 'demo.officer@example.test',
            'phoneNumber' => '09000000001',
            'role' => User::ROLE_OFFICER,
            'supervisor_id' => $supervisor->id,
            'account_status' => User::STATUS_ACTIVE,
            'password' => 'TomecoDemo!2026',
            'attendance_restrictions_enabled' => true,
            'attendance_working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
            'attendance_time_in_start' => '00:00',
            'attendance_time_in_end' => '23:59',
            'attendance_time_out_start' => '00:00',
            'attendance_time_out_end' => '23:59',
        ]);
        if ($officer->supervisor_id === null) {
            $officer->update(['supervisor_id' => $supervisor->id]);
        }

        $date = app(AttendanceRules::class)->date();
        if (! $officer->enforcerAttendances()->whereDate('attendance_date', $date)->exists()) {
            $officer->enforcerAttendances()->whereNull('time_out')->update(['time_out' => now()]);
            $officer->enforcerAttendances()->create([
                'attendance_date' => $date,
                'supervisor_id' => $officer->supervisor_id,
                'time_in' => now(),
                'time_in_latitude' => 11.245,
                'time_in_longitude' => 125.006,
            ]);
        }

        User::firstOrCreate(['username' => 'demo.admin'], [
            'fullName' => 'Casey Demo Admin',
            'email' => 'demo.admin@example.test',
            'phoneNumber' => '09000000002',
            'role' => User::ROLE_ADMIN,
            'account_status' => User::STATUS_ACTIVE,
            'password' => 'TomecoDemo!2026',
        ]);
    }
}
