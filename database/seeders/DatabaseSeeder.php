<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
        // $this->call(UsersTableSeeder::class);
        // $this->call(SecurityQuestionSeeder::class);
        // $this->call(CommissionsSeeder::class);
        // $this->call(ModulesTableSeeder::class);
        // $this->call(RolesTableSeeder::class);
        // $this->call(UsersTableSeeder::class);
        // $this->call(UsersTableUpdate::class);
        // $this->call(ProductSeeder::class);
        $this->call(WithdrawalAccountTypeSeeder::class);
    }
}
