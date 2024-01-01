<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'plancareph@gmail.com',
            'password' => Hash::make('123456'),
            'is_admin' => 1,
            'status' => 1,
            'role_id'=>1,
            'reference_code'=>'0',
            'referral_code' => '10011',
            'parent_referral'=>'0'
        ]);

        
    }
}
