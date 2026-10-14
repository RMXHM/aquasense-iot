<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Device;
use App\Models\Reading;
use App\Models\Tank;
use Illuminate\Support\Facades\DB;

/** Continued App\Services\TelemetryService helpers. */
trait TelemetryServiceHelpers
{
    private function lastValidReading(int $tankId): ?Reading
    {
        return Reading::where('tank_id', $tankId)
            ->where('valid', true)
            ->orderByDesc('measured_at')
            ->first();
    }

    private function persist(
        Tank $tank,
        Device $device,
        \DateTimeImmutable $measuredAt,
        float $distance,
        ?array $calculated,
        string $sensorStatus,
        bool $valid,
        array $payload
    ): Reading {
        return DB::transaction(function () use ($tank, $device, $measuredAt, $distance, $calculated, $sensorStatus, $valid, $payload): Reading {
            $reading = new Reading([
                'tank_id' => $tank->id,
                'device_id' => $device->id,
                'measured_at' => $measuredAt,
                'distance_cm' => round($distance, 2),
                'water_height_cm' => $calculated['water_height_cm'] ?? null,
                'percent_full' => $calculated['percent_full'] ?? null,
                'volume_litres' => $calculated['volume_litres'] ?? null,
                'volume_cubic_meters' => $calculated['volume_cubic_meters'] ?? null,
                'source' => $payload['source'] ?? $device->source,
                'sensor_status' => $sensorStatus,
                'valid' => $valid,
                'battery_percent' => $payload['battery_percent'] ?? null,
                'wifi_rssi' => $payload['wifi_rssi'] ?? null,
            ]);
            $reading->save();

            $device->forceFill([
                'last_seen_at' => $measuredAt,
                'firmware_version' => $payload['firmware_version'] ?? $device->firmware_version,
            ])->save();

            return $reading;
        });
    }

    /**
     * Deduplicate: never raise the same alert type twice within 30 minutes.
     *
     * @param  list<array{type:string,severity:string,title:string,message:string}>  $candidates
     * @return list<array>
     */
    private function raise(Tank $tank, Reading $reading, array $candidates): array
    {
        $raised = [];

        foreach ($candidates as $candidate) {
            $recent = $tank->alerts()
                ->where('type', $candidate['type'])
                ->where('created_at', '>=', now()->subMinutes(30))
                ->exists();

            if ($recent) {
                continue;
            }

            $alert = $tank->alerts()->create([
                'reading_id' => $reading->id,
                'type' => $candidate['type'],
                'severity' => $candidate['severity'],
                'title' => $candidate['title'],
                'message' => $candidate['message'],
            ]);

            $raised[] = $alert->only(['id', 'type', 'severity', 'title', 'message', 'created_at']);
        }

        return $raised;
    }

    /** @return array{low_water_threshold_percent:int,high_water_threshold_percent:int} */
    private function thresholds(Tank $tank): array
    {
        return [
            'low_water_threshold_percent' => (int) $tank->low_water_threshold_percent,
            'high_water_threshold_percent' => (int) $tank->high_water_threshold_percent,
        ];
    }

    private function present(Reading $reading, Tank $tank): array
    {
        return [
            'id' => $reading->id,
            'tank_id' => $tank->id,
            'measured_at' => $reading->measured_at?->toIso8601String(),
            'distance_cm' => $reading->distance_cm,
            'water_height_cm' => $reading->water_height_cm,
            'percent_full' => $reading->percent_full,
            'volume_litres' => $reading->volume_litres,
            'volume_cubic_meters' => $reading->volume_cubic_meters,
            'capacity_litres' => $tank->capacity_litres,
            'source' => $reading->source,
            'sensor_status' => $reading->sensor_status,
            'valid' => $reading->valid,
            'battery_percent' => $reading->battery_percent,
            'wifi_rssi' => $reading->wifi_rssi,
        ];
    }
}
