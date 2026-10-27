<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class CashierSampleSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('The sample cashier account can only be created in local or testing environments.');
        }

        $existing = User::query()->where('email', 'cashier@example.com')->first();

        if ($existing) {
            if (! $existing->isCashier()) {
                throw new RuntimeException('cashier@example.com is already used by another role. No account was changed.');
            }

            $this->command?->info('Sample cashier already exists. Its password and account details were not changed.');

            return;
        }

        if (User::query()->where('username', 'cashier.sample')->orWhere('phoneNumber', '09999990001')->exists()) {
            throw new RuntimeException('The sample username or phone number is already in use. No account was changed.');
        }

        $password = Str::password(16);

        User::create([
            'firstName' => 'Sample',
            'lastName' => 'Cashier',
            'username' => 'cashier.sample',
            'email' => 'cashier@example.com',
            'phoneNumber' => '09999990001',
            'role' => User::ROLE_CASHIER,
            'account_status' => User::STATUS_ACTIVE,
            'password' => $password,
        ]);

        $this->command?->info('Sample cashier created. Email: cashier@example.com');
        $this->command?->line('Initial password (shown once): '.$password);
    }
}
