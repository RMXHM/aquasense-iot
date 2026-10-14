<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tank extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'shape', 'radius_cm', 'length_cm', 'width_cm', 'height_cm',
        'sensor_height_cm', 'capacity_litres', 'low_water_threshold_percent',
        'high_water_threshold_percent', 'sudden_drop_percent', 'stale_after_minutes',
        'monitoring_enabled', 'demo',
    ];

    protected function casts(): array
    {
        return [
            'radius_cm' => 'float',
            'length_cm' => 'float',
            'width_cm' => 'float',
            'height_cm' => 'float',
            'sensor_height_cm' => 'float',
            'capacity_litres' => 'float',
            'monitoring_enabled' => 'boolean',
            'demo' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /** Geometry array consumed by App\Domain\LevelCalculator. */
    public function geometry(): array
    {
        return [
            'shape' => $this->shape,
            'radius_cm' => $this->radius_cm,
            'length_cm' => $this->length_cm,
            'width_cm' => $this->width_cm,
            'height_cm' => (float) $this->height_cm,
            'sensor_height_cm' => (float) $this->sensor_height_cm,
        ];
    }
}
