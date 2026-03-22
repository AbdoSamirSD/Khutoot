<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RouteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            \App\Models\Route::create([
                'name' => 'Route ' . $i,
                'source' => 'Location ' . $i,
                'destination' => 'Location ' . ($i + 5),
            ]);
        }
    }
}
