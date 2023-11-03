<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ModulesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modules = [
            ['name' => 'Home',              'url' => ''],
            ['name' => 'Teams',             'url' => 'teams'],
            ['name' => 'View Team',         'url' => 'team/:id'],
            ['name' => 'Transactions',      'url' => 'transactions'],
            ['name' => 'Users',             'url' => 'users'],
            ['name' => 'Settings',          'url' => 'settings'],
            ['name' => 'Shop',              'url' => 'shop'],
        ];
        foreach ($modules as $module) {
            Module::create($module);
        }
    }
}
