<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 0; $i < 5; $i++) {
            \App\Models\Admin::create([
                'name' => 'Admin ' . $i,
                'phone' => '123-456-7890',
                'email' => 'admin' . $i . '@example.com',
                'password' => bcrypt('password'),
            ]);
        }
    }
}
