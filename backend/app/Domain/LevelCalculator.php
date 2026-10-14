<?php

declare(strict_types=1);

namespace App\Domain;

use InvalidArgumentException;

/**
 * Converts an ultrasonic/ToF distance measurement into water level and volume.
 *
 * Physical model
 * --------------
 * The sensor is mounted above the water, pointing down at the surface.
 * It reports the distance to the *water surface*, not the water height:
 *
 *     water_height = sensor_height_cm - distance_cm
 *
 * Volume depends on the tank shape:
 *   - vertical cylinder: V = pi * r^2 * h
 *   - rectangle/cuboid: V = l * w * h
 *
 * 1 cm^3 = 1 mL, therefore 1000 cm^3 = 1 L and 1e6 cm^3 = 1 m^3.
 */
final class LevelCalculator
{
    /**
     * Water height in cm from sensor geometry and measured distance.
     *
     * @throws InvalidArgumentException when the distance is outside 0..sensor_height
     */
    public static function waterHeightCm(float $distanceCm, float $sensorHeightCm): float
    {
        if ($distanceCm < 0.0) {
            throw new InvalidArgumentException('distance_cm must not be negative');
        }
        if ($sensorHeightCm <= 0.0) {
            throw new InvalidArgumentException('sensor_height_cm must be positive');
        }
        if ($distanceCm > $sensorHeightCm) {
            throw new InvalidArgumentException(sprintf(
                'distance_cm (%.2f) exceeds sensor height (%.2f) - no echo or misconfiguration',
                $distanceCm,
                $sensorHeightCm
            ));
        }

        return $sensorHeightCm - $distanceCm;
    }

    /**
     * Percentage of the usable water column that is filled, clamped to 0..100.
     */
    public static function percentFull(float $waterHeightCm, float $tankHeightCm): float
    {
        if ($tankHeightCm <= 0.0) {
            throw new InvalidArgumentException('tank height must be positive');
        }

        $pct = ($waterHeightCm / $tankHeightCm) * 100.0;

        return round(min(100.0, max(0.0, $pct)), 2);
    }

    /**
     * Total capacity in litres for the configured geometry.
     *
     * @param  array{shape:string,radius_cm:?float,length_cm:?float,width_cm:?float,height_cm:float}  $geometry
     */
    public static function capacityLitres(array $geometry): float
    {
        $height = (float) $geometry['height_cm'];
        if ($height <= 0.0) {
            throw new InvalidArgumentException('tank height must be positive');
        }

        $areaCm2 = self::crossSectionCm2($geometry);

        return self::cm3ToLitres($areaCm2 * $height);
    }

    /**
     * Volume in litres at a given water height.
     *
     * @param  array{shape:string,radius_cm:?float,length_cm:?float,width_cm:?float}  $geometry
     */
    public static function volumeLitres(float $waterHeightCm, array $geometry): float
    {
        if ($waterHeightCm < 0.0) {
            throw new InvalidArgumentException('water height must not be negative');
        }

        $areaCm2 = self::crossSectionCm2($geometry);

        return round(self::cm3ToLitres($areaCm2 * $waterHeightCm), 2);
    }

    /**
     * Cross-sectional area of the water surface, in cm^2.
     *
     * @param  array{shape:string,radius_cm:?float,length_cm:?float,width_cm:?float}  $geometry
     */
    public static function crossSectionCm2(array $geometry): float
    {
        $shape = strtolower((string) ($geometry['shape'] ?? 'cylinder'));

        return match ($shape) {
            'cylinder' => self::circleAreaCm2((float) $geometry['radius_cm']),
            'rectangle' => self::rectangleAreaCm2(
                $geometry['length_cm'] === null ? null : (float) $geometry['length_cm'],
                $geometry['width_cm'] === null ? null : (float) $geometry['width_cm']
            ),
            default => throw new InvalidArgumentException("Unsupported tank shape: {$shape}"),
        };
    }

    /**
     * Area of a circle: pi * r^2.
     */
    public static function circleAreaCm2(float $radiusCm): float
    {
        if ($radiusCm <= 0.0) {
            throw new InvalidArgumentException('radius_cm must be positive for a cylinder');
        }

        return M_PI * $radiusCm * $radiusCm;
    }

    /**
     * Area of a rectangle: l * w.
     */
    public static function rectangleAreaCm2(?float $lengthCm, ?float $widthCm): float
    {
        if ($lengthCm === null || $widthCm === null || $lengthCm <= 0.0 || $widthCm <= 0.0) {
            throw new InvalidArgumentException('length_cm and width_cm are required for a rectangle');
        }

        return $lengthCm * $widthCm;
    }

    public static function cm3ToLitres(float $cm3): float
    {
        return $cm3 / 1000.0;
    }

    public static function litresToCubicMeters(float $litres): float
    {
        return $litres / 1000.0;
    }

    /**
     * Full pipeline: distance -> height -> percent -> litres -> m^3.
     *
     * @param  array{shape:string,radius_cm:?float,length_cm:?float,width_cm:?float,height_cm:float,sensor_height_cm:float}  $tank
     * @return array{distance_cm:float,water_height_cm:float,percent_full:float,volume_litres:float,capacity_litres:float,volume_cubic_meters:float}
     */
    public static function fromDistance(float $distanceCm, array $tank): array
    {
        $height = self::waterHeightCm($distanceCm, (float) $tank['sensor_height_cm']);
        $percent = self::percentFull($height, (float) $tank['height_cm']);
        $litres = self::volumeLitres($height, $tank);
        $capacity = self::capacityLitres($tank);

        return [
            'distance_cm' => round($distanceCm, 2),
            'water_height_cm' => round($height, 2),
            'percent_full' => $percent,
            'volume_litres' => $litres,
            'capacity_litres' => round($capacity, 2),
            'volume_cubic_meters' => round(self::litresToCubicMeters($litres), 4),
        ];
    }
}
