<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'tank_id', 'serial_number', 'activation_code', 'device_token', 'firmware_version',
        'model', 'status', 'source', 'reporting_interval_minutes', 'last_seen_at',
    ];

    protected $hidden = ['device_token', 'activation_code'];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    public function tank(): BelongsTo
    {
        return $this->belongsTo(Tank::class);
    }
}
