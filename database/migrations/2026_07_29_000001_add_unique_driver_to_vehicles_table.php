<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('vehicles')
            ->select('driver_id', DB::raw('COUNT(*) as total'))
            ->whereNotNull('driver_id')
            ->groupBy('driver_id')
            ->having('total', '>', 1)
            ->pluck('driver_id')
            ->all();

        if ($duplicates) {
            throw new RuntimeException('No se puede crear la restriccion uno a uno: hay conductores asignados a varios vehiculos. Driver IDs duplicados: '.implode(', ', $duplicates));
        }

        Schema::table('vehicles', function (Blueprint $table) {
            $table->unique('driver_id', 'vehicles_driver_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropUnique('vehicles_driver_id_unique');
        });
    }
};
