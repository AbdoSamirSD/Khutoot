<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Str;

class BusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 0; $i < 10; $i++) {
            \App\Models\Bus::create([
                'license_plate' => 'ABC-' . strtoupper(Str::random()),
                'driver_id' => rand(1, 5),
                'seating_capacity' => rand(20, 50),
            ]);
        }
    }
}
