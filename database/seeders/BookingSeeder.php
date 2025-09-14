<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BookingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 0; $i < 10; $i++) {
            \App\Models\Booking::create([
                'user_id' => rand(1, 5),
                'trip_instance_id' => rand(1, 10),
                'seat_id' => rand(1, 50),
                'status' => 'confirmed',
                'start_station_id' => rand(1, 5),
                'end_station_id' => rand(1, 5),
                'price' => rand(100, 500),
            ]);
        }
    }
}
