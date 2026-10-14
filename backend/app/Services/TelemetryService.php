<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\AlertEngine;
use App\Domain\LevelCalculator;
use App\Domain\ReadingValidator;
use App\Models\Device;
use App\Models\Reading;
use App\Models\Tank;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use App\Services\TelemetryServiceHelpers;


/**
 * The single entry point for a measurement entering AquaSense.
 *
 * Both the software simulator and a future physical ESP32 device POST to
 * /api/v1/telemetry and go through ingest(), so the dashboard never needs to
 * know where a reading came from.
 */
final class TelemetryService
{
    use TelemetryServiceHelpers;

    /**
     * @param  array{
     *   serial_number:string, device_token:string,
     *   distance_cm:numeric, measured_at?:?string,
     *   source?:string, sensor_status?:string,
     *   battery_percent?:?int, wifi_rssi?:?int, firmware_version?:?string
     * }  $payload
     * @return array{accepted:bool,reading:?array,alerts:list<array>,reason:?string}
     */
    public function ingest(array $payload, ?int $maxAgeMinutes = null): array
    {
        $device = Device::where('serial_number', $payload['serial_number'] ?? '')
            ->where('device_token', $payload['device_token'] ?? '')
            ->first();

        if (! $device) {
            throw new InvalidArgumentException('Unknown device or invalid device token');
        }

        $tank = $device->tank;
        if (! $tank) {
            throw new InvalidArgumentException('Device is not associated with a tank');
        }

        if (! $tank->monitoring_enabled) {
            return [
                'accepted' => false,
                'reading' => null,
                'alerts' => [],
                'reason' => 'Monitoring is paused for this tank. Enable monitoring to accept readings.',
            ];
        }

        $distance = ReadingValidator::requireNumericDistance($payload['distance_cm'] ?? null);
        $measuredAt = ! empty($payload['measured_at'])
            ? new \DateTimeImmutable((string) $payload['measured_at'])
            : new \DateTimeImmutable();

        $previous = $this->lastValidReading($tank->id);

        $validation = ReadingValidator::validate(
            distanceCm: $distance,
            tank: [
                'height_cm' => (float) $tank->height_cm,
                'sensor_height_cm' => (float) $tank->sensor_height_cm,
                'sudden_drop_percent' => (int) $tank->sudden_drop_percent,
            ],
            previousWaterHeightCm: $previous?->water_height_cm === null
                ? null : (float) $previous->water_height_cm,
            measuredAt: $measuredAt,
            maxAgeMinutes: $maxAgeMinutes ?? max(60, (int) $tank->stale_after_minutes * 2),
        );

        // Invalid readings are stored as evidence but never become the live level.
        if (! $validation['valid']) {
            $rejected = $this->persist($tank, $device, $measuredAt, $distance, null, $validation['status'], false, $payload);

            return [
                'accepted' => false,
                'reading' => $this->present($rejected, $tank),
                'alerts' => $this->raise($tank, $rejected, AlertEngine::evaluate(
                    reading: ['percent_full' => 0.0, 'volume_litres' => 0.0],
                    tank: $this->thresholds($tank),
                    sensorStatus: $validation['status'],
                    previousPercent: null,
                )),
                'reason' => $validation['message'],
            ];
        }

        try {
            $calculated = LevelCalculator::fromDistance($distance, $tank->geometry());
        } catch (InvalidArgumentException $e) {
            return ['accepted' => false, 'reading' => null, 'alerts' => [], 'reason' => $e->getMessage()];
        }

        $reading = $this->persist($tank, $device, $measuredAt, $distance, $calculated, $validation['status'], true, $payload);

        $candidates = AlertEngine::evaluate(
            reading: $calculated,
            tank: $this->thresholds($tank),
            sensorStatus: $validation['status'],
            previousPercent: $previous?->percent_full === null ? null : (float) $previous->percent_full,
        );

        return [
            'accepted' => true,
            'reading' => $this->present($reading, $tank),
            'alerts' => $this->raise($tank, $reading, $candidates),
            'reason' => $validation['message'],
        ];
    }
}
