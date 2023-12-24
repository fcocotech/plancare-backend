<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Product;
class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //

        Product::create([
            'name'=>'PlanCare Dental Package',
            'description'=>'PlanCare Dental Package',
            'price'=>'3000',
            'is_active'=>'1',
            'is_shop_active'=>'1',
            'photo_url'=>null
        ]);

    }
}
