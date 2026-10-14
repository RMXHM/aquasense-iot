<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\TankSimulator;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Tank;
use App\Services\SimulationService;
use App\Services\TelemetryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AquaSenseController extends Controller
{
    public function __construct(
        private readonly TelemetryService $telemetry,
        private readonly SimulationService $simulation,
    ) {}

    /** POST /api/v1/telemetry - the single hardware/simulator ingestion endpoint. */
    public function telemetry(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'serial_number' => ['required', 'string'],
            'device_token' => ['required', 'string'],
            'distance_cm' => ['required', 'numeric', 'min:0', 'max:2000'],
            'measured_at' => ['sometimes', 'nullable', 'date'],
            'source' => ['sometimes', 'in:simulation,hardware'],
            'sensor_status' => ['sometimes', 'string'],
            'battery_percent' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'wifi_rssi' => ['sometimes', 'nullable', 'integer', 'min:-120', 'max:0'],
            'firmware_version' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        try {
            $result = $this->telemetry->ingest($validated);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['device' => $e->getMessage()]);
        }

        return response()->json([
            'accepted' => $result['accepted'],
            'reason' => $result['reason'],
            'reading' => $result['reading'],
            'alerts' => $result['alerts'],
        ], $result['accepted'] ? 202 : 422);
    }

    /** POST /api/v1/tanks/{tank}/simulate/step - advance the simulator one step. */
    public function simulateStep(Request $request, Tank $tank): JsonResponse
    {
        $validated = $request->validate([
            'scenario' => ['required', 'string', 'in:'.implode(',', array_keys(TankSimulator::SCENARIOS))],
        ]);

        $step = (int) cache()->get("sim_step:{$tank->id}", 0) + 1;
        cache()->put("sim_step:{$tank->id}", $step, now()->addDay());

        return response()->json([
            'step' => $step,
            ...$this->simulation->step($tank, $validated['scenario'], $step),
        ]);
    }

    /** POST /api/v1/tanks/{tank}/simulate/reset - clear readings, alerts, step counter. */
    public function simulateReset(Tank $tank): JsonResponse
    {
        DB::transaction(function () use ($tank) {
            $tank->alerts()->delete();
            $tank->readings()->delete();
        });
        cache()->forget("sim_step:{$tank->id}");

        return response()->json(['reset' => true, 'tank_id' => $tank->id]);
    }

    /** POST /api/v1/tanks/{tank}/simulate/backfill - seed demo history instantly. */
    public function simulateBackfill(Request $request, Tank $tank): JsonResponse
    {
        $validated = $request->validate([
            'scenario' => ['required', 'string', 'in:'.implode(',', array_keys(TankSimulator::SCENARIOS))],
            'steps' => ['sometimes', 'integer', 'min:1', 'max:96'],
            'interval_minutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
        ]);

        $accepted = $this->simulation->backfill(
            tank: $tank,
            scenario: $validated['scenario'],
            steps: (int) ($validated['steps'] ?? 24),
            intervalMinutes: (int) ($validated['interval_minutes'] ?? 30),
        );

        return response()->json(['accepted' => $accepted]);
    }

    /** GET /api/v1/overview - all tanks for the signed-in demo customer. */
    public function overview(): JsonResponse
    {
        $tanks = Tank::with('devices')->orderBy('id')->get();

        return response()->json([
            'data' => $tanks->map(fn (Tank $tank) => $this->tankSummary($tank)),
            'meta' => ['generated_at' => now()->toIso8601String()],
        ]);
    }

    /** GET /api/v1/tanks/{tank} - current state plus recent history. */
    public function show(Tank $tank): JsonResponse
    {
        $tank->load('devices');

        $readings = $tank->readings()->orderByDesc('measured_at')->limit(120)->get()->reverse()->values();

        return response()->json([
            'data' => $this->tankSummary($tank),
            'history' => $readings->map(fn ($r) => [
                'measured_at' => $r->measured_at?->toIso8601String(),
                'percent_full' => $r->percent_full,
                'volume_litres' => $r->volume_litres,
                'water_height_cm' => $r->water_height_cm,
                'distance_cm' => $r->distance_cm,
                'sensor_status' => $r->sensor_status,
                'valid' => $r->valid,
            ])->all(),
            'alerts' => $tank->alerts()->orderByDesc('created_at')->limit(30)->get()
                ->map(fn ($a) => $a->only(['id', 'type', 'severity', 'title', 'message', 'acknowledged', 'created_at']))->all(),
        ]);
    }

    /** GET /api/v1/tanks/{tank}/readings - raw measurement log. */
    public function readings(Tank $tank, Request $request): JsonResponse
    {
        $limit = min(500, max(1, (int) $request->query('limit', 100)));

        return response()->json([
            'data' => $tank->readings()->orderByDesc('measured_at')->limit($limit)->get()
                ->map(fn ($r) => $r->only([
                    'id', 'measured_at', 'distance_cm', 'water_height_cm', 'percent_full',
                    'volume_litres', 'volume_cubic_meters', 'source', 'sensor_status', 'valid',
                    'battery_percent', 'wifi_rssi',
                ]))->all(),
        ]);
    }

    /** PATCH /api/v1/tanks/{tank}/settings - update config or pause monitoring. */
    public function updateSettings(Request $request, Tank $tank): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'shape' => ['sometimes', 'in:cylinder,rectangle'],
            'radius_cm' => ['sometimes', 'nullable', 'numeric', 'min:1'],
            'length_cm' => ['sometimes', 'nullable', 'numeric', 'min:1'],
            'width_cm' => ['sometimes', 'nullable', 'numeric', 'min:1'],
            'height_cm' => ['sometimes', 'numeric', 'min:1'],
            'sensor_height_cm' => ['sometimes', 'numeric', 'min:1'],
            'low_water_threshold_percent' => ['sometimes', 'integer', 'min:1', 'max:99'],
            'high_water_threshold_percent' => ['sometimes', 'integer', 'min:2', 'max:100'],
            'sudden_drop_percent' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'stale_after_minutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
            'monitoring_enabled' => ['sometimes', 'boolean'],
            'reporting_interval_minutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
        ]);

        $tank->fill($validated)->save();

        // Recalculate capacity whenever geometry changes.
        if (array_intersect(array_keys($validated), ['shape', 'radius_cm', 'length_cm', 'width_cm', 'height_cm'])) {
            $tank->capacity_litres = \App\Domain\LevelCalculator::capacityLitres($tank->geometry());
            $tank->save();
        }

        if (isset($validated['reporting_interval_minutes']) && $tank->devices()->exists()) {
            $tank->devices()->update([
                'reporting_interval_minutes' => (int) $validated['reporting_interval_minutes'],
            ]);
        }

        return response()->json(['data' => $this->tankSummary($tank->fresh(['devices']))]);
    }

    /** POST /api/v1/devices/claim - prototype device activation flow. */
    public function claimDevice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'serial_number' => ['required', 'string'],
            'activation_code' => ['required', 'string'],
            'tank_name' => ['required', 'string', 'max:255'],
            'shape' => ['required', 'in:cylinder,rectangle'],
            'radius_cm' => ['required_if:shape,cylinder', 'nullable', 'numeric', 'min:1'],
            'length_cm' => ['required_if:shape,rectangle', 'nullable', 'numeric', 'min:1'],
            'width_cm' => ['required_if:shape,rectangle', 'nullable', 'numeric', 'min:1'],
            'height_cm' => ['required', 'numeric', 'min:1'],
            'sensor_height_cm' => ['required', 'numeric', 'min:1'],
        ]);

        $device = Device::where('serial_number', $validated['serial_number'])
            ->where('activation_code', $validated['activation_code'])
            ->first();

        if (! $device) {
            throw ValidationException::withMessages([
                'activation_code' => 'This device and activation code cannot be verified.',
            ]);
        }

        if ($device->tank_id !== null && $device->status === 'active') {
            throw ValidationException::withMessages([
                'serial_number' => 'This device has already been claimed.',
            ]);
        }

        $tank = Tank::create([
            'user_id' => 1,
            'name' => $validated['tank_name'],
            'shape' => $validated['shape'],
            'radius_cm' => $validated['radius_cm'] ?? null,
            'length_cm' => $validated['length_cm'] ?? null,
            'width_cm' => $validated['width_cm'] ?? null,
            'height_cm' => $validated['height_cm'],
            'sensor_height_cm' => $validated['sensor_height_cm'],
            'capacity_litres' => 0,
            'demo' => true,
        ]);

        $tank->capacity_litres = \App\Domain\LevelCalculator::capacityLitres($tank->geometry());
        $tank->save();
        $device->update(['tank_id' => $tank->id, 'status' => 'active']);

        return response()->json([
            'data' => $this->tankSummary($tank->fresh(['devices'])),
            'notice' => 'Prototype activation only. Production must issue single-use, expiring activation codes over a verified channel.',
        ], 201);
    }

    /** POST /api/v1/alerts/{alert}/acknowledge */
    public function acknowledgeAlert(int $alertId): JsonResponse
    {
        $alert = \App\Models\Alert::findOrFail($alertId);
        $alert->update(['acknowledged' => true, 'acknowledged_at' => now()]);

        return response()->json(['acknowledged' => true, 'id' => $alert->id]);
    }

    /** GET /api/v1/scenarios - list available simulator scenarios. */
    public function scenarios(): JsonResponse
    {
        return response()->json(['data' => TankSimulator::SCENARIOS]);
    }

    /** GET /api/v1/tanks/{tank}/alerts */
    public function alerts(Tank $tank): JsonResponse
    {
        return response()->json([
            'data' => $tank->alerts()->orderByDesc('created_at')->limit(50)->get()
                ->map(fn ($a) => $a->only(['id', 'type', 'severity', 'title', 'message', 'acknowledged', 'created_at']))->all(),
        ]);
    }

    /**
     * Shared tank projection: current state, staleness and derived volume.
     */
    private function tankSummary(Tank $tank): array
    {
        $latest = $tank->readings()->where('valid', true)->orderByDesc('measured_at')->first();
        $device = $tank->devices->first();

        $staleAfter = (int) $tank->stale_after_minutes;
        $minutesSince = $latest?->measured_at?->diffInMinutes(now());

        $online = $device?->last_seen_at !== null
            && $device->last_seen_at->diffInMinutes(now()) <= max($staleAfter, (int) $device->reporting_interval_minutes * 3);

        $stale = $latest !== null && $minutesSince !== null && $minutesSince > $staleAfter;

        return [
            'id' => $tank->id,
            'name' => $tank->name,
            'shape' => $tank->shape,
            'height_cm' => (float) $tank->height_cm,
            'sensor_height_cm' => (float) $tank->sensor_height_cm,
            'capacity_litres' => round((float) $tank->capacity_litres, 2),
            'low_water_threshold_percent' => (int) $tank->low_water_threshold_percent,
            'high_water_threshold_percent' => (int) $tank->high_water_threshold_percent,
            'monitoring_enabled' => (bool) $tank->monitoring_enabled,
            'demo' => (bool) $tank->demo,
            'current' => $latest ? [
                'percent_full' => $latest->percent_full,
                'volume_litres' => $latest->volume_litres,
                'volume_cubic_meters' => $latest->volume_cubic_meters,
                'water_height_cm' => $latest->water_height_cm,
                'distance_cm' => $latest->distance_cm,
                'measured_at' => $latest->measured_at?->toIso8601String(),
                'source' => $latest->source,
                'sensor_status' => $latest->sensor_status,
            ] : null,
            'device' => $device ? [
                'serial_number' => $device->serial_number,
                'model' => $device->model,
                'status' => $device->status,
                'source' => $device->source,
                'firmware_version' => $device->firmware_version,
                'reporting_interval_minutes' => (int) $device->reporting_interval_minutes,
                'last_seen_at' => $device->last_seen_at?->toIso8601String(),
                'battery_percent' => $latest?->battery_percent,
                'wifi_rssi' => $latest?->wifi_rssi,
            ] : null,
            'state' => [
                'device_online' => (bool) $online,
                'readings_stale' => $stale,
                'minutes_since_reading' => $minutesSince,
                'unacknowledged_alerts' => $tank->alerts()->where('acknowledged', false)->count(),
            ],
            'condition' => $this->condition($tank, $latest, (bool) $online, $stale),
        ];
    }

    private function condition(Tank $tank, ?\App\Models\Reading $latest, bool $online, bool $stale): string
    {
        if (! $latest) {
            return 'No Data';
        }
        if ($stale || ! $online) {
            return 'Sensor Offline';
        }
        if (! $latest->valid) {
            return 'Sensor Error';
        }

        $percent = (float) $latest->percent_full;

        if ($percent <= (float) $tank->low_water_threshold_percent) {
            return 'Low Water';
        }
        if ($percent >= (float) $tank->high_water_threshold_percent) {
            return 'Nearly Full';
        }

        return 'Normal';
    }
}
