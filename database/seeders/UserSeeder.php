<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 0; $i <= 10; $i++) {
            \App\Models\User::create([
                'name' => 'User ' . $i,
                'phone' => rand(1000000000, 9999999999),
                'email' => 'user' . $i . '@example.com',
                'password' => bcrypt('password'),
                'city' => 'cairo'
            ]);
        }
    }
}
