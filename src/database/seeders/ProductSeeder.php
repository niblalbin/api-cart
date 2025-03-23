<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('products')->insert([
            [
                'ecommerce_id' => 1,
                'product_sku' => 'SP001',
                'product_name' => 'Spare Part Type A',
                'product_category_id' => 1, // spare_parts
                'quantity' => 100,
                'base_price' => 100.00
            ],
            [
                'ecommerce_id' => 1,
                'product_sku' => 'RF001',
                'product_name' => 'Refrigeration Unit X',
                'product_category_id' => 2, // refrigeration
                'quantity' => 50,
                'base_price' => 100.00
            ],
            [
                'ecommerce_id' => 1,
                'product_sku' => 'PV001',
                'product_name' => 'Solar Panel Basic',
                'product_category_id' => 3, // photovoltaic
                'quantity' => 200,
                'base_price' => 100.00
            ]
        ]);
    }
}