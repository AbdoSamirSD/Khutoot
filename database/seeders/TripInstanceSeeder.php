<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TripInstanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 11; $i <= 20; $i++) {
            \App\Models\TripInstance::create([
                'trip_id' => rand(1, 10),
                'departure_time' => now()->addMinutes($i * 10),
                'arrival_time' => now()->addMinutes($i * 20),
                'status' => 'on_going',
                'total_seats' => 50,
                'booked_seats' => 40,
                'available_seats' => 10,
            ]);
        }
    }
}
