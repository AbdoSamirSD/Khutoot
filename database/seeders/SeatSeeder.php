<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SeatSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 1; $i <= 50; $i++) {
            \App\Models\Seat::create([
                'bus_id' => rand(1, 10),
                'seat_number' => $i,
                'status' => rand(0, 1) ? 'available' : 'used',
            ]);
        }
    }
}
