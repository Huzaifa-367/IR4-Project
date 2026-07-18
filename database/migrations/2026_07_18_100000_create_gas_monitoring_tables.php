<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gas_readings', function (Blueprint $table) {
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

        Schema::create('gas_reading_rollups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->timestamp('bucket_start');
            $table->decimal('lel_min', 6, 2)->nullable();
            $table->decimal('lel_avg', 6, 2)->nullable();
            $table->decimal('lel_max', 6, 2)->nullable();
            $table->decimal('h2s_min', 8, 2)->nullable();
            $table->decimal('h2s_avg', 8, 2)->nullable();
            $table->decimal('h2s_max', 8, 2)->nullable();
            $table->decimal('o2_min', 5, 2)->nullable();
            $table->decimal('o2_avg', 5, 2)->nullable();
            $table->decimal('o2_max', 5, 2)->nullable();
            $table->decimal('co_min', 8, 2)->nullable();
            $table->decimal('co_avg', 8, 2)->nullable();
            $table->decimal('co_max', 8, 2)->nullable();
            $table->decimal('co2_min', 10, 2)->nullable();
            $table->decimal('co2_avg', 10, 2)->nullable();
            $table->decimal('co2_max', 10, 2)->nullable();
            $table->unsignedInteger('sample_count')->default(0);
            $table->timestamps();
            $table->unique(['device_id', 'bucket_start']);
        });

        Schema::create('gas_thresholds', function (Blueprint $table) {
            $table->id();
            $table->string('gas_type');
            $table->decimal('warning_level', 10, 2);
            $table->decimal('alarm_level', 10, 2);
            $table->string('unit');
            $table->string('direction')->default('above');
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['gas_type']);
        });

        Schema::create('gas_alarms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gas_type');
            $table->string('level');
            $table->decimal('reading_value', 10, 2);
            $table->decimal('threshold_value', 10, 2);
            $table->timestamp('triggered_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('alert_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->boolean('during_outage')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['device_id', 'gas_type', 'level']);
            $table->index(['triggered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gas_alarms');
        Schema::dropIfExists('gas_thresholds');
        Schema::dropIfExists('gas_reading_rollups');
        Schema::dropIfExists('gas_readings');
    }
};
