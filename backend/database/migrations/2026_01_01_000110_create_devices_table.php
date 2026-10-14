<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tank_id')->nullable()->constrained()->nullOnDelete();
            $table->string('serial_number')->unique();
            // Plain activation code used by the prototype claim flow.
            $table->string('activation_code');
            // Secret bearer token the device presents on every telemetry POST.
            $table->string('device_token', 64)->unique();
            $table->string('firmware_version')->nullable();
            $table->string('model')->default('AquaSense-S3');
            $table->string('status')->default('unclaimed'); // unclaimed|active|paused|retired
            $table->string('source')->default('simulation'); // simulation|hardware
            $table->unsignedSmallInteger('reporting_interval_minutes')->default(5);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
