<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DriverSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 0; $i < 10; $i++) {
            \App\Models\Driver::create([
                'name' => 'Driver ' . $i,
                'license_number' => 'ABC123' . $i,
                'phone' => rand(1000000000, 9999999999),
                'email' => 'driver' . $i . '@example.com',
                'password' => bcrypt('password'),
            ]);
        }
    }
}
