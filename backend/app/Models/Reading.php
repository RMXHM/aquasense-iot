<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reading extends Model
{
    use HasFactory;

    protected $fillable = [
        'tank_id', 'device_id', 'measured_at', 'distance_cm', 'water_height_cm',
        'percent_full', 'volume_litres', 'volume_cubic_meters', 'source',
        'sensor_status', 'valid', 'battery_percent', 'wifi_rssi',
    ];

    protected function casts(): array
    {
        return [
            'measured_at' => 'datetime',
            'distance_cm' => 'float',
            'water_height_cm' => 'float',
            'percent_full' => 'float',
            'volume_litres' => 'float',
            'volume_cubic_meters' => 'float',
            'valid' => 'boolean',
        ];
    }

    public function tank(): BelongsTo
    {
        return $this->belongsTo(Tank::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
