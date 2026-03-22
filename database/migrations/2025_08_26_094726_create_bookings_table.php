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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('trip_instance_id')->constrained('trip_instances')->onDelete('cascade');
            $table->foreignId('seat_id')->constrained('seats')->onDelete('cascade');
            // $table->foreignId('route_station_id')->constrained('route_stations')->onDelete('cascade');
            // $table->Integer('start_station_order')->nullable();
            // $table->Integer('end_station_order')->nullable();
            $table->integer('price')->default(0);
            $table->enum('status', ['pending', 'confirmed', 'canceled'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
