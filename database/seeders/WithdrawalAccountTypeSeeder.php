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
            ['name' => 'Bank Transfer'],
            ['name' => 'E-Wallet'],
            ['name' => 'Cash Pickup'],
            ['name' => 'Payment Centers'],
        ];

        foreach ($accounts as $account) {
            WithdrawalAccountType::create($account);
        }
    }
}
