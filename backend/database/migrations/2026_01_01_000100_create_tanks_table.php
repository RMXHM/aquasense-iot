<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tanks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Shape: 'cylinder' (vertical circular) or 'rectangle' (cuboid).
            $table->string('shape')->default('cylinder');
            // Geometry in centimetres. radius_cm is used for cylinder,
            // length_cm + width_cm for rectangle.
            $table->decimal('radius_cm', 10, 2)->nullable();
            $table->decimal('length_cm', 10, 2)->nullable();
            $table->decimal('width_cm', 10, 2)->nullable();
            // Internal height of the tank (usable water column), in cm.
            $table->decimal('height_cm', 10, 2);
            // Distance from the (top-mounted) sensor face to the tank bottom, in cm.
            // water_height = sensor_height_cm - measured_distance_cm
            $table->decimal('sensor_height_cm', 10, 2);
            // Denormalised total capacity in litres, derived from geometry.
            $table->decimal('capacity_litres', 12, 2);
            // Percentage of water column below which a low-water alert is raised.
            $table->unsignedTinyInteger('low_water_threshold_percent')->default(20);
            // Percentage at or above which an overflow-risk alert is raised.
            $table->unsignedTinyInteger('high_water_threshold_percent')->default(95);
            // A drop of this many percent between consecutive readings is "unusual".
            $table->unsignedTinyInteger('sudden_drop_percent')->default(15);
            // Minutes without a reading after which a tank is considered stale.
            $table->unsignedSmallInteger('stale_after_minutes')->default(30);
            $table->boolean('monitoring_enabled')->default(true);
            $table->boolean('demo')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'demo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tanks');
    }
};
