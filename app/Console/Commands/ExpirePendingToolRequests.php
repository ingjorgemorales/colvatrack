<?php

namespace App\Console\Commands;

use App\Services\ToolRequestService;
use Illuminate\Console\Command;

class ExpirePendingToolRequests extends Command
{
    protected $signature = 'requests:expire-pending {--minutes=30 : Minutos maximos para aceptar una solicitud pendiente}';

    protected $description = 'Marca como vencidas las solicitudes pendientes que superan el tiempo de aceptacion.';

    public function handle(ToolRequestService $service): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $expired = $service->expirePendingOlderThan($minutes);

        $this->info('Solicitudes vencidas: '.$expired);

        return self::SUCCESS;
    }
}
