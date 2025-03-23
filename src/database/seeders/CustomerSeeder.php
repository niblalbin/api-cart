<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('customers')->insert([
            [
                'customer_role_id' => 1, // private
                'email' => 'private@example.com',
                'psw' => Hash::make('password123')
            ],
            [
                'customer_role_id' => 2, // business
                'email' => 'business@example.com',
                'psw' => Hash::make('password123')
            ],
        ]);
    }
}