<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CartStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('cart_status')->insert([
            ['status' => 'created'],
            ['status' => 'building'],
            ['status' => 'checkout']
        ]);
    }
}