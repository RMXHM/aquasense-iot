<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\TankSimulator;
use App\Models\Tank;

/**
 * Turns simulator scenario steps into real telemetry payloads.
 *
 * A device returns distance, never litres. This mirrors exactly what a real
 * ESP32 firmware would send, so exercising the simulator exercises the
 * hardware path too.
 */
final class SimulationService
{
    public function __construct(private readonly TelemetryService $telemetry) {}

    /**
     * Advance a tank's demo device by one simulation step.
     *
     * @return array{accepted:bool,reason:?string,reading:?array,alerts:list<array>}
     */
    public function step(Tank $tank, string $scenario, int $step): array
    {
        $device = $tank->devices()->first();
        if (! $device) {
            return ['accepted' => false, 'reason' => 'Tank has no associated device.', 'reading' => null, 'alerts' => []];
        }

        $failed = TankSimulator::shouldFail($scenario, $step);

        $payload = [
            'serial_number' => $device->serial_number,
            'device_token' => $device->device_token,
            'source' => 'simulation',
            'firmware_version' => $device->firmware_version ?? 'aquasense-sim/1.0.0',
            'battery_percent' => TankSimulator::batteryAtStep($step),
            'wifi_rssi' => $failed ? -88 : -58,
            'measured_at' => now()->toIso8601String(),
            // distance 0 == no echo, the same convention real firmware uses.
            'distance_cm' => $failed ? 0 : TankSimulator::distanceForPercent(
                TankSimulator::percentAtStep($scenario, $step),
                (float) $tank->sensor_height_cm,
                (float) $tank->height_cm,
            ),
        ];

        return $this->telemetry->ingest($payload);
    }

    /** Generate several steps at once so a chart has history immediately. */
    public function backfill(Tank $tank, string $scenario, int $steps = 24, int $intervalMinutes = 30): int
    {
        $device = $tank->devices()->first();
        if (! $device) {
            return 0;
        }

        $count = 0;
        // Seeding history intentionally creates older readings, so relax the
        // arrival-staleness window for the whole batch.
        $maxAge = ($steps * $intervalMinutes) + 60;
        for ($i = 0; $i < $steps; $i++) {
            $percent = TankSimulator::percentAtStep($scenario, $i);
            $failed = TankSimulator::shouldFail($scenario, $i);

            $result = $this->telemetry->ingest(maxAgeMinutes: $maxAge, payload: [
                'serial_number' => $device->serial_number,
                'device_token' => $device->device_token,
                'source' => 'simulation',
                'firmware_version' => $device->firmware_version ?? 'aquasense-sim/1.0.0',
                'battery_percent' => TankSimulator::batteryAtStep($i),
                'wifi_rssi' => $failed ? -88 : -58,
                // The newest reading must land shortly before "now", otherwise the
                // tank would immediately look offline right after seeding history.
                'measured_at' => now()
                    ->subMinutes(($intervalMinutes * ($steps - $i - 1)) + 1)
                    ->toIso8601String(),
                'distance_cm' => $failed ? 0 : TankSimulator::distanceForPercent(
                    $percent,
                    (float) $tank->sensor_height_cm,
                    (float) $tank->height_cm,
                ),
            ]);

            if ($result['accepted']) {
                $count++;
            }
        }

        return $count;
    }
}
