<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TrackingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            \App\Models\Tracking::create([
                'trip_instance_id' => rand(1, 10),
                'current_station_id' => rand(1, 10),
                'status' => 'arrived',
                'last_updated' => now()->addMinutes($i * 10),
            ]);
        }
    }
}
