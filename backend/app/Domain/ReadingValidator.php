<?php

declare(strict_types=1);

namespace App\Domain;

use InvalidArgumentException;

/**
 * Validates incoming sensor readings before they are trusted or stored.
 *
 * Devices are dumb: they report a distance. Everything implausible must be
 * rejected or flagged here rather than being rendered as a real water level.
 */
final class ReadingValidator
{
    /** A sensor returning 0 means "no echo received" (timeout), not "empty tank". */
    public const NO_ECHO = 'no_echo';

    public const OUT_OF_RANGE = 'out_of_range';

    public const IMPLAUSIBLE_DROP = 'implausible_drop';

    /** Readings older than this are considered stale on arrival. */
    public const STALE = 'stale';

    public const OK = 'ok';

    /**
     * @param  array{height_cm:float,sensor_height_cm:float,sudden_drop_percent:int}  $tank
     * @return array{status:string,valid:bool,message:?string}
     */
    public static function validate(
        float $distanceCm,
        array $tank,
        ?float $previousWaterHeightCm = null,
        ?\DateTimeInterface $measuredAt = null,
        int $maxAgeMinutes = 60
    ): array {
        if ($distanceCm === 0.0) {
            return [
                'status' => self::NO_ECHO,
                'valid' => false,
                'message' => 'Sensor reported no echo. The surface may be out of range or the sensor is obstructed.',
            ];
        }

        $sensorHeight = (float) $tank['sensor_height_cm'];
        $tankHeight = (float) $tank['height_cm'];

        if ($distanceCm < 0.0 || $distanceCm > $sensorHeight) {
            return [
                'status' => self::OUT_OF_RANGE,
                'valid' => false,
                'message' => sprintf(
                    'Distance %.1f cm is outside the configured 0-%.1f cm range. Check sensor mounting or calibration.',
                    $distanceCm,
                    $sensorHeight
                ),
            ];
        }

        $waterHeight = $sensorHeight - $distanceCm;

        if ($waterHeight > $tankHeight + 0.01) {
            return [
                'status' => self::OUT_OF_RANGE,
                'valid' => false,
                'message' => sprintf(
                    'Water height %.1f cm exceeds tank height %.1f cm. Sensor height is likely misconfigured.',
                    $waterHeight,
                    $tankHeight
                ),
            ];
        }

        if ($measuredAt !== null) {
            $ageMinutes = (time() - $measuredAt->getTimestamp()) / 60;
            if ($ageMinutes > $maxAgeMinutes) {
                return [
                    'status' => self::STALE,
                    'valid' => false,
                    'message' => sprintf(
                        'Reading is %.0f minutes old, newer than the %d minute limit. It was discarded instead of shown as live.',
                        $ageMinutes,
                        $maxAgeMinutes
                    ),
                ];
            }
        }

        if ($previousWaterHeightCm !== null && $previousWaterHeightCm > 0.0) {
            $dropPercent = (($previousWaterHeightCm - $waterHeight) / $previousWaterHeightCm) * 100.0;
            $limit = (float) ($tank['sudden_drop_percent'] ?? 15);

            if ($dropPercent >= $limit) {
                return [
                    'status' => self::IMPLAUSIBLE_DROP,
                    'valid' => true,
                    'message' => sprintf(
                        'Water level fell %.1f%% in a single interval (threshold %.0f%%). Recorded as an unusual change, not a confirmed leak.',
                        $dropPercent,
                        $limit
                    ),
                ];
            }
        }

        return ['status' => self::OK, 'valid' => true, 'message' => null];
    }

    /**
     * Throw a domain-specific exception that the API layer maps to HTTP 422.
     */
    public static function requireNumericDistance(mixed $value): float
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException('distance_cm must be a number');
        }

        return (float) $value;
    }
}
