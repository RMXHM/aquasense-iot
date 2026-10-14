<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Device;
use Illuminate\Console\Command;

/**
 * Prints the local demo device credentials so the software simulator can
 * authenticate. Development convenience only - device tokens are never exposed
 * by the API itself.
 */
class DeviceCredentialsCommand extends Command
{
    protected $signature = 'aquasense:device-credentials {tank? : Tank id} {--json}';

    protected $description = 'Show the demo device serial and token for the software simulator';

    public function handle(): int
    {
        $tankId = $this->argument('tank');
        $device = $tankId
            ? Device::where('tank_id', (int) $tankId)->first()
            : Device::where('status', 'active')->first();

        if (! $device) {
            $this->error('No device found. Run: php artisan db:seed --class=AquaSenseDemoSeeder');

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'serial_number' => $device->serial_number,
                'device_token' => $device->device_token,
                'tank_id' => $device->tank_id,
            ], JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->table(
            ['Field', 'Value'],
            [
                ['Tank', (string) $device->tank_id],
                ['Serial', $device->serial_number],
                ['Token', $device->device_token],
            ]
        );

        return self::SUCCESS;
    }
}
