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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_number')->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('trip_instance_id')->constrained('trip_instances')->onDelete('cascade');
            $table->foreignId('trip_id')->constrained('trips')->onDelete('cascade');
            $table->enum('type', ['bus', 'driver', 'service', 'other']);
            $table->text('description')->nullable();
            $table->text('suggestions')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'resolved', 'closed'])->default('pending');
            $table->foreignId('admin_id')->nullable()->constrained('admins')->onDelete('set null');
            $table->text('admin_notes')->nullable();
            $table->string('attachment')->nullable();
            $table->unique(['user_id', 'trip_instance_id', 'type'], 'unique_user_trip_type');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
