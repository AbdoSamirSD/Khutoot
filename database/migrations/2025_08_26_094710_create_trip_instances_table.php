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
        Schema::create('trip_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->onDelete('cascade');
            $table->timestamp('departure_time');
            $table->timestamp('arrival_time');
            $table->enum('status', ['upcoming', 'on_going', 'completed', 'canceled'])->default('upcoming');
            $table->integer('total_seats')->default(0);
            $table->integer('booked_seats')->default(0);
            $table->integer('available_seats')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trip_instances');
    }
};
