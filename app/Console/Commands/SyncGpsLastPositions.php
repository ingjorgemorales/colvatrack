<?php

namespace App\Console\Commands;

use App\Services\GpsServiceTrackService;
use Illuminate\Console\Command;

class SyncGpsLastPositions extends Command
{
    protected $signature = 'gps:sync-last-positions {--moviles= : Lista de moviles separados por coma para esta corrida}';
    protected $description = 'Sincroniza ultimas posiciones GPS desde ServiceTrack Triplog.';

    public function handle(GpsServiceTrackService $service): int
    {
        try {
            $result = $service->syncLastPositions($this->option('moviles'));
            $this->info('GPS sincronizado: '.json_encode($result, JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        } catch (\Throwable $e) {
            report($e);
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
