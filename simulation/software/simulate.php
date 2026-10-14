#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * AquaSense standalone software simulator.
 *
 * Sends readings to the API using exactly the same payload a physical ESP32
 * firmware would send: a distance in centimetres, never a litre value.
 *
 * Usage:
 *   php simulate.php --tank=1 --scenario=consumption --interval=5
 *   php simulate.php --tank=1 --scenario=refill --interval=5 --steps=20
 *   php simulate.php --tank=1 --scenario=offline --duration=60
 *   php simulate.php --list
 */

$apiBase = rtrim((string) (getenv('AQUASENSE_API') ?: 'http://127.0.0.1:8000/api/v1'), '/');

$options = getopt('', ['tank::', 'scenario::', 'interval::', 'steps::', 'duration::', 'list', 'api::', 'help']);

if (isset($options['help'])) {
    echo <<<TXT
AquaSense software simulator

  --tank=<id>        Tank to drive (default 1)
  --scenario=<name>  One of the scenarios listed by --list
  --interval=<sec>   Seconds between readings (default 5)
  --steps=<n>        Stop after n readings (default: run forever)
  --duration=<sec>   Stop after this many seconds
  --api=<url>        API base URL (default {$apiBase})
  --list             List scenarios and exit

TXT;
    exit(0);
}

if (isset($options['api'])) {
    $apiBase = rtrim($options['api'], '/');
}

$listUrl = $apiBase.'/scenarios';
$ch = curl_init($listUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_CONNECTTIMEOUT => 5,
]);
$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($status !== 200) {
    fwrite(STDERR, "Cannot reach the AquaSense API at {$apiBase} (HTTP {$status}).\n");
    fwrite(STDERR, "Start it first:  php artisan serve --host=127.0.0.1 --port=8000  (run from backend/)\n");
    exit(1);
}

$scenarios = json_decode((string) $response, true)['data'] ?? [];

if (isset($options['list'])) {
    echo "Available scenarios:\n";
    foreach ($scenarios as $key => $description) {
        printf("  %-16s %s\n", $key, $description);
    }
    exit(0);
}

$tankId = (int) ($options['tank'] ?? 1);
$scenario = (string) ($options['scenario'] ?? 'normal');
$interval = max(1, (int) ($options['interval'] ?? 5));
$maxSteps = isset($options['steps']) ? (int) $options['steps'] : null;
$maxSeconds = isset($options['duration']) ? (int) $options['duration'] : null;

if (! array_key_exists($scenario, $scenarios)) {
    fwrite(STDERR, "Unknown scenario '{$scenario}'. Run with --list to see the options.\n");
    exit(1);
}

// Device credentials come from the API so the simulator never hardcodes a secret.
function fetch_device(string $apiBase, int $tankId): array
{
    $ch = curl_init($apiBase.'/tanks/'.$tankId);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200) {
        throw new RuntimeException("Tank {$tankId} not found (HTTP {$status})");
    }

    $data = json_decode((string) $body, true)['data'];
    $device = $data['device'] ?? null;

    if (! $device) {
        throw new RuntimeException("Tank {$tankId} has no device");
    }

    return $data;
}

/**
 * Load demo credentials from the backend so no secret is committed here.
 */
function load_credentials(int $tankId): array
{
    $backend = __DIR__.'/../../backend';
    if (! is_dir($backend)) {
        fwrite(STDERR, "Backend directory not found at {$backend}\n");
        exit(1);
    }

    // Use an absolute path to artisan and a clean environment: the simulator may be
    // launched from any working directory, and `php artisan` relative lookup is not
    // reliable there.
    $artisan = $backend.'/artisan';
    $cmd = sprintf('cd %s && php %s aquasense:device-credentials %d --json 2>&1', escapeshellarg($backend), escapeshellarg($artisan), $tankId);
    $json = shell_exec($cmd);
    $data = json_decode((string) $json, true);

    if (! is_array($data) || empty($data['device_token'])) {
        fwrite(STDERR, "Could not read device credentials for tank {$tankId}.\n");
        fwrite(STDERR, "Seed the demo data first: cd backend && php artisan db:seed --class=AquaSenseDemoSeeder\n");
        exit(1);
    }

    return $data;
}

/**
 * Deterministic scenario maths - mirrors App\Domain\TankSimulator in the API.
 */
function percent_at_step(string $scenario, int $step): float
{
    return match ($scenario) {
        'normal' => 68.0 - (0.15 * $step),
        'consumption' => 72.0 - (1.6 * $step),
        'refill' => min(96.0, 22.0 + (4.2 * $step)),
        'low_water' => max(2.0, 18.0 - (1.1 * $step)),
        'sudden_drop' => $step === 0 ? 74.0 : max(4.0, 74.0 - (22.0 * $step)),
        'overflow_risk' => min(99.5, 90.0 + (1.2 * $step)),
        default => 65.0,
    };
}

function should_fail(string $scenario, int $step): bool
{
    return $scenario === 'sensor_error' && $step % 3 === 1;
}

/** distance = sensor_height - tank_height * percent/100 */
function distance_for_percent(float $percent, float $sensorHeightCm, float $tankHeightCm): float
{
    $percent = min(100.0, max(0.0, $percent));

    return round($sensorHeightCm - ($tankHeightCm * ($percent / 100.0)), 2);
}

function post_reading(string $apiBase, array $creds, float $distance, int $step): array
{
    $payload = json_encode([
        'serial_number' => $creds['serial_number'],
        'device_token' => $creds['device_token'],
        'distance_cm' => $distance,
        'measured_at' => gmdate('c'),
        'source' => 'simulation',
        'firmware_version' => 'aquasense-software-sim/1.0.0',
        'battery_percent' => max(5, 96 - (int) round(0.05 * $step)),
        'wifi_rssi' => $distance === 0.0 ? -88 : -58,
    ]);

    $ch = curl_init($apiBase.'/telemetry');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return ['http' => 0, 'accepted' => false, 'reason' => 'transport error: '.$error];
    }

    $decoded = json_decode((string) $body, true) ?: [];

    return [
        'http' => $status,
        'accepted' => (bool) ($decoded['accepted'] ?? false),
        'reason' => $decoded['reason'] ?? null,
        'reading' => $decoded['reading'] ?? null,
        'alerts' => $decoded['alerts'] ?? [],
    ];
}

// ---------------------------------------------------------------- run loop
$tank = fetch_device($apiBase, $tankId);
$creds = load_credentials($tankId);

$sensorHeight = (float) $tank['sensor_height_cm'];
$tankHeight = (float) $tank['height_cm'];

printf("AquaSense simulator | tank %d (%s) | scenario '%s' | every %ds\n", $tankId, $tank['name'], $scenario, $interval);
printf("Geometry: %s, height %.1f cm, sensor at %.1f cm, capacity %.1f L\n", $tank['shape'], $tankHeight, $sensorHeight, $tank['capacity_litres']);
echo "Ctrl+C to stop.\n\n";

$step = 0;
$start = time();
$offlineGap = false;

while (true) {
    $step++;

    if ($scenario === 'offline') {
        // Alternates between reporting and going quiet to exercise stale state.
        $offlineGap = ! $offlineGap;
        if ($offlineGap) {
            printf("[%s] step %-3d simulating device disconnection (no telemetry)\n", date('H:i:s'), $step);
            if ($maxSteps !== null && $step >= $maxSteps) {
                break;
            }
            sleep($interval);
            continue;
        }
    }

    $failed = should_fail($scenario, $step);
    $percent = percent_at_step($scenario, $step);
    $distance = $failed ? 0.0 : distance_for_percent($percent, $sensorHeight, $tankHeight);

    $result = post_reading($apiBase, $creds, $distance, $step);
    $http = $result['http'];
    $accepted = $result['accepted'];

    $label = $failed
        ? 'sensor failure (no echo)'
        : sprintf('distance %.1f cm', $distance);

    if ($accepted) {
        $reading = $result['reading'];
        printf(
            "[%s] step %-3d %-26s -> %5.1f%%  %8.1f L  (HTTP %d)\n",
            date('H:i:s'),
            $step,
            $label,
            (float) ($reading['percent_full'] ?? 0),
            (float) ($reading['volume_litres'] ?? 0),
            $http
        );
    } else {
        printf(
            "[%s] step %-3d %-26s -> rejected (HTTP %d): %s\n",
            date('H:i:s'),
            $step,
            $label,
            $http,
            (string) ($result['reason'] ?? 'unknown')
        );
    }

    foreach ($result['alerts'] as $alert) {
        printf("           !! [%s] %s - %s\n", $alert['severity'], $alert['title'], $alert['message']);
    }

    if ($maxSteps !== null && $step >= $maxSteps) {
        break;
    }
    if ($maxSeconds !== null && (time() - $start) >= $maxSeconds) {
        break;
    }

    sleep($interval);
}

echo "\nSimulator finished after {$step} steps.\n";
