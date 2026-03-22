<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('wallet_users_transactions', function (Blueprint $table) {
            //
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallet_users_transactions', function (Blueprint $table) {
            //
        });
    }
};
