<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DriverAvailabilityService;

class ExpireDriverAvailability extends Command
{
    protected $signature = 'drivers:expire-availability';

    protected $description = 'Expira automaticamente el estado ocupado (hora de almuerzo) de los conductores';

    public function handle(DriverAvailabilityService $service): int
    {
        $expired = $service->expireLunch();

        $this->info("Conductores desocupados: {$expired}");

        return self::SUCCESS;
    }
}