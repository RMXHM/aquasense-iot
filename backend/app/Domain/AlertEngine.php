<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Decides which alerts a reading should raise, and how they are worded.
 *
 * Deliberate wording rule: a sudden fall is described as an "unusual change"
 * or "suspected issue". Only a human should ever call something a leak.
 */
final class AlertEngine
{
    public const TYPE_LOW_WATER = 'low_water';
    public const TYPE_SUDDEN_DROP = 'sudden_drop';
    public const TYPE_OVERFLOW_RISK = 'overflow_risk';
    public const TYPE_SENSOR_ERROR = 'sensor_error';
    public const TYPE_DEVICE_OFFLINE = 'device_offline';
    public const TYPE_REFILL = 'refill_detected';

    /**
     * @param  array{percent_full:float,water_height_cm:float}  $reading
     * @param  array{low_water_threshold_percent:int,high_water_threshold_percent:int}  $tank
     * @return list<array{type:string,severity:string,title:string,message:string}>
     */
    public static function evaluate(array $reading, array $tank, string $sensorStatus, ?float $previousPercent = null): array
    {
        $alerts = [];

        if (in_array($sensorStatus, [ReadingValidator::NO_ECHO, ReadingValidator::OUT_OF_RANGE], true)) {
            $alerts[] = [
                'type' => self::TYPE_SENSOR_ERROR,
                'severity' => 'critical',
                'title' => 'Sensor reading rejected',
                'message' => 'The sensor did not return a usable measurement. The tank shows the last known good value and is not being presented as live.',
            ];

            return $alerts;
        }

        $percent = (float) $reading['percent_full'];
        $litres = round((float) ($reading['volume_litres'] ?? 0), 1);

        if ($sensorStatus === ReadingValidator::IMPLAUSIBLE_DROP) {
            $alerts[] = [
                'type' => self::TYPE_SUDDEN_DROP,
                'severity' => 'critical',
                'title' => 'Unusual water level change',
                'message' => sprintf(
                    'The level fell unusually fast to %.0f%% (about %s L). This is flagged as an unexplained change - it is not automatically a leak. Check for an open valve, a burst fitting, or a displaced sensor.',
                    $percent,
                    number_format($litres, 1)
                ),
            ];
        }

        if ($percent <= (float) $tank['low_water_threshold_percent']) {
            $alerts[] = [
                'type' => self::TYPE_LOW_WATER,
                'severity' => 'warning',
                'title' => 'Low water level',
                'message' => sprintf(
                    'Water level is %.0f%% (about %s L), at or below your %d%% warning threshold. Consider scheduling a refill.',
                    $percent,
                    number_format($litres, 1),
                    (int) $tank['low_water_threshold_percent']
                ),
            ];
        }

        if ($percent >= (float) $tank['high_water_threshold_percent']) {
            $alerts[] = [
                'type' => self::TYPE_OVERFLOW_RISK,
                'severity' => 'warning',
                'title' => 'Tank nearly full',
                'message' => sprintf(
                    'Water level is %.0f%%, close to the top of the tank. If a pump or float valve is filling, there is an overflow risk.',
                    $percent
                ),
            ];
        }

        if ($previousPercent !== null && $percent - $previousPercent >= 10.0) {
            $alerts[] = [
                'type' => self::TYPE_REFILL,
                'severity' => 'info',
                'title' => 'Refill detected',
                'message' => sprintf(
                    'Water level rose from %.0f%% to %.0f%%. The tank appears to have been refilled.',
                    $previousPercent,
                    $percent
                ),
            ];
        }

        return $alerts;
    }

    /**
     * Alert raised when no reading has arrived within the stale window.
     *
     * @return array{type:string,severity:string,title:string,message:string}
     */
    public static function offlineAlert(int $staleMinutes): array
    {
        return [
            'type' => self::TYPE_DEVICE_OFFLINE,
            'severity' => 'warning',
            'title' => 'Device offline',
            'message' => sprintf(
                'No measurement has arrived for more than %d minutes. This battery-powered device reports periodically, so a brief gap can be normal.',
                $staleMinutes
            ),
        ];
    }
}
