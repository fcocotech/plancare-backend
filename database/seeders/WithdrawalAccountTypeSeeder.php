<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\WithdrawalAccountType;

class WithdrawalAccountTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        WithdrawalAccountType::truncate();

        $accounts = [
            ['name' => 'Cash Pickup'],
            ['name' => 'Bank Transfer/Deposit'],
            ['name' => 'E-Wallet'],
            ['name' => 'Payment Centers'],
            ['name' => 'Other'],
        ];

        foreach ($accounts as $account) {
            WithdrawalAccountType::create($account);
        }
    }
}
