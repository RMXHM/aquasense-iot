<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\LevelCalculator;
use PHPUnit\Framework\TestCase;

class LevelCalculatorTest extends TestCase
{
    /** A vertical cylinder: r=60 cm, h=150 cm -> pi*3600*150 cm^3 = 1696.46 L */
    public function test_cylinder_capacity_matches_geometry(): void
    {
        $litres = LevelCalculator::capacityLitres([
            'shape' => 'cylinder',
            'radius_cm' => 60.0,
            'length_cm' => null,
            'width_cm' => null,
            'height_cm' => 150.0,
        ]);

        $this->assertEqualsWithDelta(M_PI * 3600 * 150 / 1000, $litres, 0.01);
        $this->assertEqualsWithDelta(1696.46, $litres, 0.01);
    }

    /** A rectangle: 100 x 60 x 120 cm = 720,000 cm^3 = 720 L */
    public function test_rectangle_capacity_is_length_times_width_times_height(): void
    {
        $litres = LevelCalculator::capacityLitres([
            'shape' => 'rectangle',
            'radius_cm' => null,
            'length_cm' => 100.0,
            'width_cm' => 60.0,
            'height_cm' => 120.0,
        ]);

        $this->assertEqualsWithDelta(720.0, $litres, 0.01);
    }

    /** Half full cylinder holds half its capacity. */
    public function test_half_full_cylinder_is_half_volume(): void
    {
        $geometry = ['shape' => 'cylinder', 'radius_cm' => 60.0];
        $capacity = LevelCalculator::capacityLitres($geometry + ['height_cm' => 150.0]);
        $half = LevelCalculator::volumeLitres(75.0, $geometry);

        $this->assertEqualsWithDelta($capacity / 2, $half, 0.01);
    }

    /** Top-mounted sensor: distance is measured to the surface, not the bottom. */
    public function test_water_height_is_sensor_height_minus_distance(): void
    {
        $this->assertEqualsWithDelta(
            160.0 - 40.0,
            LevelCalculator::waterHeightCm(40.0, 160.0),
            0.001
        );
    }

    public function test_percent_full_is_clamped_and_scaled(): void
    {
        $this->assertEqualsWithDelta(50.0, LevelCalculator::percentFull(75.0, 150.0), 0.01);
        $this->assertEquals(100.0, LevelCalculator::percentFull(999.0, 150.0));
        $this->assertEquals(0.0, LevelCalculator::percentFull(-5.0, 150.0));
    }

    public function test_full_pipeline_returns_consistent_units(): void
    {
        $result = LevelCalculator::fromDistance(40.0, [
            'shape' => 'cylinder',
            'radius_cm' => 60.0,
            'length_cm' => null,
            'width_cm' => null,
            'height_cm' => 150.0,
            'sensor_height_cm' => 160.0,
        ]);

        // water height 120 cm of 150 cm = 80%
        $this->assertEqualsWithDelta(80.0, $result['percent_full'], 0.01);
        $this->assertEqualsWithDelta(
            $result['volume_litres'] / 1000,
            $result['volume_cubic_meters'],
            0.001
        );
        $this->assertLessThanOrEqual($result['capacity_litres'], $result['volume_litres']);
    }

    public function test_distance_beyond_sensor_height_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        LevelCalculator::waterHeightCm(200.0, 160.0);
    }

    public function test_negative_distance_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        LevelCalculator::waterHeightCm(-1.0, 160.0);
    }

    public function test_cylinder_requires_a_radius(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        LevelCalculator::crossSectionCm2(['shape' => 'cylinder', 'radius_cm' => null]);
    }

    public function test_unknown_shape_is_rejected_rather_than_assumed_linear(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        LevelCalculator::crossSectionCm2(['shape' => 'sphere', 'radius_cm' => 60.0]);
    }
}
