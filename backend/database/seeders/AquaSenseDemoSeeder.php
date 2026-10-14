<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\LevelCalculator;
use App\Models\Device;
use App\Models\Tank;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AquaSenseDemoSeeder extends Seeder
{
    /**
     * Demo credentials. These are local, non-secret placeholders used only so a
     * judge can explore the product without real hardware.
     */
    public const DEMO_SERIAL = 'AQS-S3-0001';

    public const DEMO_ACTIVATION = 'AQUA-2026-DEMO';

    public const DEMO_TOKEN = 'demo-device-token-0001';

    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'demo@aquasense.local'],
            ['name' => 'AquaSense Demo Customer', 'password' => Hash::make(Str::random(32))]
        );

        $tank = Tank::updateOrCreate(
            ['name' => 'Household Tank'],
            [
                'user_id' => $user->id,
                'shape' => 'cylinder',
                // 60 cm radius, 150 cm tall -> ~1696 L
                'radius_cm' => 60,
                'height_cm' => 150,
                'sensor_height_cm' => 160,
                'capacity_litres' => 0,
                'low_water_threshold_percent' => 20,
                'high_water_threshold_percent' => 95,
                'sudden_drop_percent' => 15,
                'stale_after_minutes' => 30,
                'monitoring_enabled' => true,
                'demo' => true,
            ]
        );
        $tank->capacity_litres = LevelCalculator::capacityLitres($tank->geometry());
        $tank->save();

        Device::updateOrCreate(
            ['serial_number' => self::DEMO_SERIAL],
            [
                'tank_id' => $tank->id,
                'activation_code' => self::DEMO_ACTIVATION,
                'device_token' => self::DEMO_TOKEN,
                'model' => 'AquaSense-S3',
                'status' => 'active',
                'source' => 'simulation',
                'firmware_version' => 'aquasense-sim/1.0.0',
                'reporting_interval_minutes' => 5,
            ]
        );

        // A second, unclaimed device so the activation flow can be demonstrated.
        Device::updateOrCreate(
            ['serial_number' => 'AQS-S3-0002'],
            [
                'tank_id' => null,
                'activation_code' => 'AQUA-2026-DEMO2',
                'device_token' => Str::random(48),
                'model' => 'AquaSense-S3',
                'status' => 'unclaimed',
                'source' => 'hardware',
                'reporting_interval_minutes' => 15,
            ]
        );
    }
}
