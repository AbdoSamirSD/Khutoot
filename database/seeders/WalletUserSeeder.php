<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WalletUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            \App\Models\WalletUser::create([
                'user_id' => rand(1, 10),
                'balance' => rand(1000, 5000)
            ]);
        }
    }
}
