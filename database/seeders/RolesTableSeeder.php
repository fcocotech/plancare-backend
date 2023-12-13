<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::truncate();

        $roles = [
            ['name' => 'Admin',   'module_ids' => '1,2,3,4,5,6,7,8,9'],
            ['name' => 'Member',  'module_ids' => '1,2,3,4,6,8,9'],
        ];
        foreach ($roles as $role) {
            Role::create($role);
        }
    }
}
