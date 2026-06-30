<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_locations', function (Blueprint $table) {
            $table->index(['vehicle_id', 'gps_datetime', 'id'], 'vloc_vehicle_gps_id_idx');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->index(['status', 'current_latitude', 'current_longitude'], 'vehicles_status_location_idx');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_locations', function (Blueprint $table) {
            $table->dropIndex('vloc_vehicle_gps_id_idx');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex('vehicles_status_location_idx');
        });
    }
};
