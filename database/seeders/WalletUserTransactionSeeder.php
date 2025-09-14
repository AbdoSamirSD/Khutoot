<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WalletUserTransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            \App\Models\WalletUserTransaction::create([
                'user_wallet_id' => rand(1, 10),
                'amount' => rand(-500, 500),
                'type' => rand(0, 1) ? 'credit' : 'debit',
            ]);
        }
    }
}
