<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tool_requests', function (Blueprint $table) {
            $table->decimal('delivery_vehicle_latitude', 10, 7)->nullable()->after('technician_longitude');
            $table->decimal('delivery_vehicle_longitude', 10, 7)->nullable()->after('delivery_vehicle_latitude');
            $table->decimal('delivery_technician_latitude', 10, 7)->nullable()->after('delivery_vehicle_longitude');
            $table->decimal('delivery_technician_longitude', 10, 7)->nullable()->after('delivery_technician_latitude');
            $table->unsignedInteger('delivery_distance_meters')->nullable()->after('delivery_technician_longitude');
        });
    }

    public function down(): void
    {
        Schema::table('tool_requests', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_vehicle_latitude',
                'delivery_vehicle_longitude',
                'delivery_technician_latitude',
                'delivery_technician_longitude',
                'delivery_distance_meters',
            ]);
        });
    }
};
