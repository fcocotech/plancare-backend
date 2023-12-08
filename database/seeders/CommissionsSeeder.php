<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Commission;

class CommissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Commission::truncate();

        $commissions = [
            ['level' => '1',    'rate' => '30'],
            ['level' => '2',    'rate' => '20'],
            ['level' => '3',    'rate' => '10'],
            ['level' => '4',    'rate' => '5'],
            ['level' => '5',    'rate' => '2.5'],
            ['level' => '6',    'rate' => '1.25'],
            ['level' => '7',    'rate' => '0.625'],
            ['level' => '8',    'rate' => '0.3125'],
            ['level' => '9',    'rate' => '0.15625'],
            ['level' => '10',   'rate' => '0.078125'],
            ['level' => '11',   'rate' => '0.030625'],
            ['level' => '12',   'rate' => '0.0195313'],
            ['level' => '13',   'rate' => '0.0097656'],
            ['level' => '14',   'rate' => '0.0048828'],
            ['level' => '15',   'rate' => '0.0024414'],
        ];

        foreach ($commissions as $commission) {
            Commission::create($commission);
        }
    }
}
