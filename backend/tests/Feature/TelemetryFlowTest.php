<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Reading;
use App\Models\Tank;
use Database\Seeders\AquaSenseDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelemetryFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AquaSenseDemoSeeder::class);
    }

    /** @return array{0:int,1:array} */
    public function sendReading(array $overrides = []): array
    {
        $payload = array_merge([
            'serial_number' => AquaSenseDemoSeeder::DEMO_SERIAL,
            'device_token' => AquaSenseDemoSeeder::DEMO_TOKEN,
            'distance_cm' => 40.0,
            'source' => 'simulation',
        ], $overrides);

        $response = $this->postJson('/api/v1/telemetry', $payload);

        return [$response->status(), $response->json()];
    }

    public function test_a_valid_reading_is_stored_and_returned_with_volume(): void
    {
        [$status, $json] = $this->sendReading();

        $this->assertSame(202, $status);
        $this->assertTrue($json['accepted']);
        $this->assertSame(1, Reading::count());

        $reading = Reading::first();
        $this->assertTrue((bool) $reading->valid);
        // sensor 160 cm, distance 40 cm -> 120 cm water of 150 cm = 80%
        $this->assertEqualsWithDelta(80.0, (float) $reading->percent_full, 0.05);
        $this->assertNotNull($reading->volume_litres);
        $this->assertSame('simulation', $reading->source);
    }

    public function test_no_echo_is_rejected_and_creates_a_critical_alert(): void
    {
        [$status, $json] = $this->sendReading(['distance_cm' => 0]);

        $this->assertSame(422, $status);
        $this->assertFalse($json['accepted']);
        $this->assertStringContainsString('no echo', $json['reason']);
        $this->assertNotEmpty($json['alerts']);
        $this->assertSame('critical', $json['alerts'][0]['severity']);
        $this->assertNull($json['reading']['percent_full']);
    }

    public function test_unknown_device_token_is_refused(): void
    {
        [$status] = $this->sendReading(['device_token' => 'wrong-token']);

        $this->assertSame(422, $status);
        $this->assertSame(0, Reading::count());
    }

    public function test_low_water_reading_raises_a_low_water_alert(): void
    {
        // 20% of the 150 cm column = 30 cm water height -> distance 160 - 30 = 130 cm
        [$status, $json] = $this->sendReading(['distance_cm' => 130.0]);

        $this->assertSame(202, $status);
        $this->assertContains('low_water', array_column($json['alerts'], 'type'));
    }

    public function test_sudden_drop_is_flagged_and_worded_as_unexplained(): void
    {
        $this->sendReading(['distance_cm' => 40.0]); // 80% full

        [$status, $json] = $this->sendReading(['distance_cm' => 130.0]); // -> ~20% full
        $this->assertContains('sudden_drop', array_column($json['alerts'], 'type'));

        $message = collect($json['alerts'])->firstWhere('type', 'sudden_drop')['message'];
        $this->assertStringContainsString('not automatically a leak', $message);
    }

    public function test_backfill_creates_a_chart_worth_of_history(): void
    {
        $response = $this->postJson('/api/v1/tanks/1/simulate/backfill', [
            'scenario' => 'consumption',
            'steps' => 24,
            'interval_minutes' => 15,
        ]);

        $response->assertOk();
        $this->assertSame(24, $response->json('accepted'));
        $this->assertSame(24, Reading::where('valid', true)->count());
    }

    public function test_paused_monitoring_rejects_readings(): void
    {
        $this->patchJson('/api/v1/tanks/1/settings', ['monitoring_enabled' => false])->assertOk();

        [$status, $json] = $this->sendReading();

        $this->assertSame(422, $status);
        $this->assertStringContainsString('paused', $json['reason']);
    }

    public function test_stale_reading_is_not_shown_as_live(): void
    {
        // Tighten the stale window, then send a reading old enough to be accepted
        // (under the 60 minute arrival limit) but already stale for display.
        $this->patchJson('/api/v1/tanks/1/settings', ['stale_after_minutes' => 30])->assertOk();
        $this->sendReading(['distance_cm' => 40.0, 'measured_at' => now()->subMinutes(40)->toIso8601String()]);

        $overview = $this->getJson('/api/v1/overview')->assertOk()->json('data.0');
        $this->assertTrue($overview['state']['readings_stale']);
        $this->assertSame('Sensor Offline', $overview['condition']);
    }

    public function test_reading_far_older_than_the_arrival_limit_is_discarded(): void
    {
        [$status, $json] = $this->sendReading([
            'distance_cm' => 40.0,
            'measured_at' => now()->subHours(5)->toIso8601String(),
        ]);

        $this->assertSame(422, $status);
        $this->assertFalse($json['accepted']);
        $this->assertSame(0, Reading::where('valid', true)->count());
    }

    public function test_simulator_step_advances_and_reset_clears_data(): void
    {
        $this->postJson('/api/v1/tanks/1/simulate/step', ['scenario' => 'normal'])
            ->assertOk()->assertJsonPath('step', 1);

        $this->postJson('/api/v1/tanks/1/simulate/reset')->assertOk();
        $this->assertSame(0, Reading::count());
    }

    public function test_device_claim_associates_unclaimed_device_with_new_tank(): void
    {
        $response = $this->postJson('/api/v1/devices/claim', [
            'serial_number' => 'AQS-S3-0002',
            'activation_code' => 'AQUA-2026-DEMO2',
            'tank_name' => 'Garden Tank',
            'shape' => 'rectangle',
            'length_cm' => 120,
            'width_cm' => 80,
            'height_cm' => 100,
            'sensor_height_cm' => 110,
        ]);

        $response->assertCreated();
        // 120 x 80 x 100 cm = 960,000 cm^3 = 960 L
        $this->assertEqualsWithDelta(960.0, $response->json('data.capacity_litres'), 0.5);
        $this->assertSame(2, Tank::count());
    }

    public function test_claim_with_wrong_activation_code_fails(): void
    {
        $this->postJson('/api/v1/devices/claim', [
            'serial_number' => 'AQS-S3-0002',
            'activation_code' => 'WRONG',
            'tank_name' => 'Nope',
            'shape' => 'cylinder',
            'radius_cm' => 50,
            'height_cm' => 120,
            'sensor_height_cm' => 130,
        ])->assertStatus(422);
    }

    public function test_overview_reports_capacity_for_the_demo_tank(): void
    {
        $overview = $this->getJson('/api/v1/overview')->assertOk()->json('data.0');

        $this->assertSame('Household Tank', $overview['name']);
        $this->assertEqualsWithDelta(1696.46, $overview['capacity_litres'], 0.05);
        $this->assertTrue($overview['demo']);
    }
}
