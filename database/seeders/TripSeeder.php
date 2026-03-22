<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TripSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            \App\Models\Trip::create([
                'bus_id' => rand(1, 10),
                'driver_id' => rand(1, 10),
                'route_id' => rand(1, 5),
                'departure_time' => now()->addMinutes($i * 10),
                'arrival_time' => now()->addMinutes($i * 20),
                'price' => rand(100, 500)
            ]);
        }
    }
}
