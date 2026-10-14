<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Deterministic tank simulator.
 *
 * Each scenario is a closed-form function of the step index, so a scenario
 * always produces the same sequence of distances for the same tank. There is
 * no randomness: every value shown in the UI can be traced back to a step.
 *
 * The simulator works in *percent of water column* and converts to the
 * distance the sensor would report, so it stays valid for any tank geometry.
 */
final class TankSimulator
{
    public const SCENARIOS = [
        'normal' => 'Normal operation - steady consumption',
        'consumption' => 'Gradual water consumption',
        'refill' => 'Refill - water level rising',
        'low_water' => 'Low water warning',
        'sudden_drop' => 'Sudden abnormal level drop',
        'overflow_risk' => 'Near full / overflow risk',
        'sensor_error' => 'Invalid or failed sensor reading',
        'offline' => 'Device disconnection',
    ];

    /**
     * Percentage of the water column filled at a given step.
     */
    public static function percentAtStep(string $scenario, int $step): float
    {
        return match ($scenario) {
            'normal' => 68.0 - (0.15 * $step),
            'consumption' => 72.0 - (1.6 * $step),
            'refill' => min(96.0, 22.0 + (4.2 * $step)),
            'low_water' => max(2.0, 18.0 - (1.1 * $step)),
            'sudden_drop' => $step === 0 ? 74.0 : max(4.0, 74.0 - (22.0 * $step)),
            'overflow_risk' => min(99.5, 90.0 + (1.2 * $step)),
            'sensor_error' => 65.0,
            'offline' => 60.0,
            default => 65.0,
        };
    }

    /**
     * True when this step should fail rather than report a distance.
     * A failed step sends distance 0, which the API treats as "no echo".
     */
    public static function shouldFail(string $scenario, int $step): bool
    {
        return $scenario === 'sensor_error' && $step % 3 === 1;
    }

    /**
     * Convert a target water-column percentage into the distance a top-mounted
     * sensor would report.
     *
     * water_height = sensor_height * percent/100
     * distance     = sensor_height - water_height
     */
    public static function distanceForPercent(float $percent, float $sensorHeightCm, float $tankHeightCm): float
    {
        $percent = min(100.0, max(0.0, $percent));
        $waterHeight = $tankHeightCm * ($percent / 100.0);

        return round($sensorHeightCm - $waterHeight, 2);
    }

    /**
     * Simulated battery drain: about 0.05 percentage points per step, floor 5%.
     */
    public static function batteryAtStep(int $step, int $startPercent = 96): int
    {
        return max(5, $startPercent - (int) round(0.05 * $step));
    }
}
