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
        $roles = [
            ['name' => 'Admin',   'module_ids' => '1,2,3,4,5,6,7'],
            ['name' => 'Member',  'module_ids' => '1,2,3,4,6,7'],
        ];
        foreach ($roles as $role) {
            Role::create($role);
        }
    }
}
