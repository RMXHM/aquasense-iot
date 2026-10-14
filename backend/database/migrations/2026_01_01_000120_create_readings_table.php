<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tank_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            // When the device measured. Server receipt time is created_at.
            $table->timestamp('measured_at');
            // Raw sensor output: distance from sensor to water surface, in cm.
            $table->decimal('distance_cm', 10, 2);
            // Derived values.
            $table->decimal('water_height_cm', 10, 2)->nullable();
            $table->decimal('percent_full', 6, 2)->nullable();
            $table->decimal('volume_litres', 12, 2)->nullable();
            $table->decimal('volume_cubic_meters', 12, 4)->nullable();
            $table->string('source')->default('simulation'); // simulation|hardware
            // ok|no_echo|out_of_range|implausible_drop
            $table->string('sensor_status')->default('ok');
            $table->boolean('valid')->default(true);
            $table->unsignedTinyInteger('battery_percent')->nullable();
            $table->smallInteger('wifi_rssi')->nullable();
            $table->timestamps();

            $table->index(['tank_id', 'measured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('readings');
    }
};
