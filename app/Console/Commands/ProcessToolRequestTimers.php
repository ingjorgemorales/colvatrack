<?php

namespace App\Console\Commands;

use App\Services\ToolRequestService;
use Illuminate\Console\Command;

class ProcessToolRequestTimers extends Command
{
    protected $signature = 'requests:process-timers {--finalize-minutes=2 : Minutos maximos para finalizar despues de recogida}';

    protected $description = 'Detecta demoras activas y finaliza automaticamente solicitudes recogidas.';

    public function handle(ToolRequestService $service): int
    {
        $delays = $service->detectActiveDelays();
        $finalized = $service->autoFinalizePickedUpOlderThan(max(1, (int) $this->option('finalize-minutes')));

        $this->info('Demoras creadas: '.$delays);
        $this->info('Solicitudes finalizadas automaticamente: '.$finalized);

        return self::SUCCESS;
    }
}
