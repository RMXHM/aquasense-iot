<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\AlertEngine;
use App\Domain\TankSimulator;
use PHPUnit\Framework\TestCase;

class AlertEngineTest extends TestCase
{
    private function thresholds(): array
    {
        return ['low_water_threshold_percent' => 20, 'high_water_threshold_percent' => 95];
    }

    public function test_low_water_raises_a_warning_with_litres(): void
    {
        $alerts = AlertEngine::evaluate(
            reading: ['percent_full' => 12.0, 'volume_litres' => 203.6],
            tank: $this->thresholds(),
            sensorStatus: 'ok',
        );

        $this->assertCount(1, $alerts);
        $this->assertSame(AlertEngine::TYPE_LOW_WATER, $alerts[0]['type']);
        $this->assertSame('warning', $alerts[0]['severity']);
    }

    public function test_overflow_risk_near_the_top(): void
    {
        $alerts = AlertEngine::evaluate(
            reading: ['percent_full' => 98.0, 'volume_litres' => 1600.0],
            tank: $this->thresholds(),
            sensorStatus: 'ok',
        );

        $this->assertContains(AlertEngine::TYPE_OVERFLOW_RISK, array_column($alerts, 'type'));
    }

    public function test_sensor_failure_short_circuits_and_is_critical(): void
    {
        $alerts = AlertEngine::evaluate(
            reading: ['percent_full' => 0.0, 'volume_litres' => 0.0],
            tank: $this->thresholds(),
            sensorStatus: 'no_echo',
        );

        $this->assertCount(1, $alerts);
        $this->assertSame('critical', $alerts[0]['severity']);
    }

    public function test_refill_is_reported_as_information_not_a_leak(): void
    {
        $alerts = AlertEngine::evaluate(
            reading: ['percent_full' => 80.0, 'volume_litres' => 1300.0],
            tank: $this->thresholds(),
            sensorStatus: 'ok',
            previousPercent: 40.0,
        );

        $this->assertContains(AlertEngine::TYPE_REFILL, array_column($alerts, 'type'));
    }

    public function test_normal_level_raises_nothing(): void
    {
        $this->assertSame([], AlertEngine::evaluate(
            reading: ['percent_full' => 65.0, 'volume_litres' => 1100.0],
            tank: $this->thresholds(),
            sensorStatus: 'ok',
        ));
    }

    public function test_simulator_scenarios_are_deterministic(): void
    {
        $this->assertSame(
            TankSimulator::percentAtStep('consumption', 5),
            TankSimulator::percentAtStep('consumption', 5),
        );
        $this->assertSame(74.0, TankSimulator::percentAtStep('sudden_drop', 0));
    }

    public function test_refill_scenario_rises_monotonically(): void
    {
        $previous = -INF;
        for ($i = 0; $i < 10; $i++) {
            $current = TankSimulator::percentAtStep('refill', $i);
            $this->assertGreaterThanOrEqual($previous, $current);
            $previous = $current;
        }
    }

    public function test_consumption_scenario_falls(): void
    {
        $this->assertLessThan(
            TankSimulator::percentAtStep('consumption', 0),
            TankSimulator::percentAtStep('consumption', 10),
        );
    }

    public function test_distance_conversion_inverts_the_height_formula(): void
    {
        // 50% of a 150 cm tank with the sensor at 160 cm -> distance 85 cm.
        $distance = TankSimulator::distanceForPercent(50.0, 160.0, 150.0);

        $this->assertEqualsWithDelta(85.0, $distance, 0.01);
    }

    public function test_sensor_error_scenario_fails_only_on_selected_steps(): void
    {
        $this->assertFalse(TankSimulator::shouldFail('sensor_error', 0));
        $this->assertTrue(TankSimulator::shouldFail('sensor_error', 1));
        $this->assertFalse(TankSimulator::shouldFail('normal', 1));
    }
}
