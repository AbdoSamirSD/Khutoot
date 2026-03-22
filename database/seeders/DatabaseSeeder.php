<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        $this->call([
            AdminSeeder::class,
            UserSeeder::class,
            DriverSeeder::class,
            WalletUserSeeder::class,
            WalletDriverSeeder::class,
            WalletUserTransactionSeeder::class,
            WalletDriverTransactionSeeder::class,
            BusSeeder::class,
            RouteSeeder::class,
            StationSeeder::class,
            RouteStationSeeder::class,
            SeatSeeder::class,
            TripSeeder::class,
            TripInstanceSeeder::class,
            BookingSeeder::class,
            TicketSeeder::class,
            TrackingSeeder::class,
        ]);

    }


}
