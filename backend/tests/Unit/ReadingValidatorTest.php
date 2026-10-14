<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\ReadingValidator;
use PHPUnit\Framework\TestCase;

class ReadingValidatorTest extends TestCase
{
    private function tank(): array
    {
        return [
            'height_cm' => 150.0,
            'sensor_height_cm' => 160.0,
            'sudden_drop_percent' => 15,
        ];
    }

    public function test_zero_distance_means_no_echo_not_empty_tank(): void
    {
        $result = ReadingValidator::validate(0.0, $this->tank());

        $this->assertSame(ReadingValidator::NO_ECHO, $result['status']);
        $this->assertFalse($result['valid']);
    }

    public function test_distance_greater_than_sensor_height_is_out_of_range(): void
    {
        $result = ReadingValidator::validate(200.0, $this->tank());

        $this->assertSame(ReadingValidator::OUT_OF_RANGE, $result['status']);
        $this->assertFalse($result['valid']);
    }

    public function test_normal_reading_is_valid(): void
    {
        $result = ReadingValidator::validate(40.0, $this->tank());

        $this->assertSame(ReadingValidator::OK, $result['status']);
        $this->assertTrue($result['valid']);
    }

    public function test_sudden_drop_is_flagged_but_recorded(): void
    {
        // Previous height 120 cm, new distance 100 -> height 60 cm: a 50% fall.
        $result = ReadingValidator::validate(100.0, $this->tank(), previousWaterHeightCm: 120.0);

        $this->assertSame(ReadingValidator::IMPLAUSIBLE_DROP, $result['status']);
        $this->assertTrue($result['valid'], 'The reading is kept, but flagged for review.');
        $this->assertStringContainsString('not a confirmed leak', $result['message']);
    }

    public function test_small_normal_decline_is_not_flagged(): void
    {
        // 120 cm -> 115 cm is a ~4% fall, below the 15% threshold.
        $result = ReadingValidator::validate(45.0, $this->tank(), previousWaterHeightCm: 120.0);

        $this->assertSame(ReadingValidator::OK, $result['status']);
    }

    public function test_old_reading_is_rejected_as_stale(): void
    {
        $result = ReadingValidator::validate(
            distanceCm: 40.0,
            tank: $this->tank(),
            previousWaterHeightCm: null,
            measuredAt: new \DateTimeImmutable('-3 hours'),
            maxAgeMinutes: 60,
        );

        $this->assertSame(ReadingValidator::STALE, $result['status']);
        $this->assertFalse($result['valid']);
    }

    public function test_non_numeric_distance_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ReadingValidator::requireNumericDistance('not-a-number');
    }
}
