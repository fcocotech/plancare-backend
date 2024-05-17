<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ProductCategory;

class ProductCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ProductCategory::truncate();

        $categories = [
            ['name' => 'Savers'],
            ['name' => 'Family'],
        ];
        foreach ($categories as $category) {
            ProductCategory::create($category);
        }
    }
}
