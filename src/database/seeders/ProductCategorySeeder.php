<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('product_category')->insert([
            ['category' => 'spare_parts'],
            ['category' => 'refrigeration'],
            ['category' => 'photovoltaic']
        ]);
    }
}