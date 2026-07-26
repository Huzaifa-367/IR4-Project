<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gas_readings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('recorded_at')->index();
            $table->timestamp('received_at');
            $table->decimal('lel_pct', 6, 2)->nullable();
            $table->decimal('h2s_ppm', 8, 2)->nullable();
            $table->decimal('o2_pct', 5, 2)->nullable();
            $table->decimal('co_ppm', 8, 2)->nullable();
            $table->decimal('co2_ppm', 10, 2)->nullable();
            $table->boolean('is_backfill')->default(false);
            $table->boolean('clock_skew')->default(false);
            $table->uuid('event_uid');
            $table->timestamps();
            $table->unique(['device_id', 'event_uid']);
            $table->index(['device_id', 'recorded_at']);
        });

        Schema::create('gas_thresholds', function (Blueprint $table): void {
            $table->id();
            $table->string('gas_type')->unique();
            $table->decimal('warning_level', 10, 2);
            $table->decimal('alarm_level', 10, 2);
            $table->string('unit');
            $table->string('direction')->default('above');
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('gas_alarms', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gas_type');
            $table->string('level');
            $table->decimal('reading_value', 10, 2);
            $table->decimal('threshold_value', 10, 2);
            $table->timestamp('triggered_at')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('alert_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->boolean('during_outage')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['device_id', 'gas_type', 'level']);
        });

        Schema::create('environmental_readings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('recorded_at')->index();
            $table->timestamp('received_at');
            $table->decimal('temperature_c', 5, 2)->nullable();
            $table->decimal('humidity_pct', 5, 2)->nullable();
            $table->decimal('wind_speed_ms', 6, 2)->nullable();
            $table->json('extra')->nullable();
            $table->boolean('is_backfill')->default(false);
            $table->boolean('clock_skew')->default(false);
            $table->uuid('event_uid');
            $table->timestamps();
            $table->unique(['device_id', 'event_uid']);
            $table->index(['device_id', 'recorded_at']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('environmental_readings');
        Schema::dropIfExists('gas_alarms');
        Schema::dropIfExists('gas_thresholds');
        Schema::dropIfExists('gas_readings');
    }
};
