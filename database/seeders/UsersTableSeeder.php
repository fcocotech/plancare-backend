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
        $users =[
            [
                'name' => 'Admin',
                'email' => 'plancareph@gmail.com',
                'password' => Hash::make('123456'),
                'is_admin' => 1,
                'status' => 1,
                'role_id'=>1,
                'reference_code'=>'0',
                'referral_code' => '10011',
                'parent_referral'=>'0'
            ],
            [
                'name' => 'Luis Oquias',
                'email' => 'ystepugay@gmail.com',
                'password' => Hash::make('123456'),
                'is_admin' => 0,
                'status' => 1,
                'role_id'=>2,
                'reference_code'=>'0',
                'referral_code' => '10012',
                'parent_referral'=>'1'
            ],
            [
                'name' => 'Luis Oquias',
                'email' => 'ystepugay@gmail.com',
                'password' => Hash::make('123456'),
                'is_admin' => 0,
                'status' => 1,
                'role_id'=>2,
                'reference_code'=>'0',
                'referral_code' => '10013',
                'parent_referral'=>'1'
            ],
           
            [
                'name' => 'Franco Cipriano',
                'email' => 'franco@cocotechsolutions.com',
                'password' => Hash::make('Qwe123!@#'),
                'is_admin' => 0,
                'status' => 1,
                'role_id'=>2,
                'reference_code'=>'0',
                'referral_code' => '100111',
                'parent_referral'=>'1'
            ]
        ];
        
        // User::create([
        //     'name' => 'Admin',
        //     'email' => 'plancareph@gmail.com',
        //     'password' => Hash::make('123456'),
        //     'is_admin' => 1,
        //     'status' => 1,
        //     'role_id'=>1,
        //     'reference_code'=>'0',
        //     'referral_code' => '10011',
        //     'parent_referral'=>'0'
        // ]);

        foreach($users as $user){
            User::create($user);
        }

        
    }
}
