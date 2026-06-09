<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gps_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('base_url');
            $table->string('client_code')->nullable();
            $table->text('api_key_encrypted')->nullable();
            $table->unsignedInteger('request_interval_seconds')->default(10);
            $table->unsignedInteger('daily_limit')->default(8000);
            $table->string('status')->default('active');
            $table->json('config_json')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('plate')->unique();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->year('year')->nullable();
            $table->string('color')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('gps_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_gps_id')->nullable()->index();
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('current_latitude', 10, 7)->nullable();
            $table->decimal('current_longitude', 10, 7)->nullable();
            $table->decimal('current_speed', 8, 2)->nullable();
            $table->decimal('current_heading', 8, 2)->nullable();
            $table->text('current_address')->nullable();
            $table->timestamp('last_gps_datetime')->nullable();
            $table->string('last_gps_event')->nullable();
            $table->string('imei')->nullable();
            $table->decimal('odometer', 12, 2)->nullable();
            $table->string('gps_status')->nullable();
            $table->string('gps_device_brand')->nullable();
            $table->string('gps_device_model')->nullable();
            $table->string('battery')->nullable();
            $table->string('gps_marker_url')->nullable();
            $table->timestamps();
        });

        Schema::create('gps_request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gps_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->text('requested_moviles');
            $table->string('status');
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->unsignedInteger('response_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('vehicle_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('speed', 8, 2)->nullable();
            $table->decimal('heading', 8, 2)->nullable();
            $table->text('address')->nullable();
            $table->string('gps_event')->nullable();
            $table->timestamp('gps_datetime')->nullable();
            $table->decimal('odometer', 12, 2)->nullable();
            $table->json('raw_payload_json')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('user_location_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->string('source')->default('web');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('inventory_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('unit')->default('unidad');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('vehicle_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity_total')->default(0);
            $table->unsignedInteger('quantity_available')->default(0);
            $table->unsignedInteger('quantity_reserved')->default(0);
            $table->unsignedInteger('quantity_delivered')->default(0);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['vehicle_id', 'inventory_item_id']);
        });

        Schema::create('tool_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pendiente');
            $table->string('priority')->default('normal');
            $table->decimal('technician_latitude', 10, 7);
            $table->decimal('technician_longitude', 10, 7);
            $table->text('technician_address')->nullable();
            $table->text('observation')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('en_route_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tool_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status')->default('reserved');
            $table->timestamps();
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('request_id')->nullable()->constrained('tool_requests')->nullOnDelete();
            $table->string('movement_type');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('previous_available');
            $table->unsignedInteger('new_available');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('tool_request_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_request_id')->constrained()->cascadeOnDelete();
            $table->string('old_status')->nullable();
            $table->string('new_status');
            $table->foreignId('changed_by')->constrained('users')->cascadeOnDelete();
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technician_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('message');
            $table->string('type')->default('info');
            $table->json('data_json')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('module');
            $table->text('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        foreach ([
            'audit_logs', 'notifications', 'chat_messages', 'chats', 'tool_request_status_histories',
            'inventory_movements', 'tool_request_items', 'tool_requests', 'vehicle_inventory',
            'inventory_items', 'inventory_categories', 'user_location_logs', 'vehicle_locations',
            'gps_request_logs', 'vehicles', 'gps_providers',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
