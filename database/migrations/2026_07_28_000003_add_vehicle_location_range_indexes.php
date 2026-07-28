<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_locations', function (Blueprint $table) {
            $table->index(['gps_datetime', 'vehicle_id'], 'vloc_gps_vehicle_idx');
            $table->index(['gps_datetime', 'speed', 'vehicle_id'], 'vloc_gps_speed_vehicle_idx');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_locations', function (Blueprint $table) {
            $table->dropIndex('vloc_gps_vehicle_idx');
            $table->dropIndex('vloc_gps_speed_vehicle_idx');
        });
    }
};
