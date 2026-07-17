<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tool_request_delays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_request_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('status_at_detection');
            $table->unsignedInteger('allowed_minutes');
            $table->unsignedInteger('elapsed_minutes');
            $table->text('reason');
            $table->string('status')->default('active');
            $table->timestamp('state_started_at')->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['tool_request_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_request_delays');
    }
};
