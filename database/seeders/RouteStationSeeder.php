<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RouteStationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            \App\Models\RouteStation::create([
                'route_id' => $i,
                'station_id' => rand(1, 10),
                'station_order' => $i,
                'arrival_time' => now()->addMinutes(rand(1, 120)),
                'departure_time' => now()->addMinutes(rand(121, 240)),
            ]);
        }
    }
}
