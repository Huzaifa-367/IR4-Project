<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('environmental_readings', function (Blueprint $table) {
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

        Schema::create('environmental_rollups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->timestamp('bucket_start');
            $table->decimal('temp_min', 5, 2)->nullable();
            $table->decimal('temp_avg', 5, 2)->nullable();
            $table->decimal('temp_max', 5, 2)->nullable();
            $table->decimal('humidity_min', 5, 2)->nullable();
            $table->decimal('humidity_avg', 5, 2)->nullable();
            $table->decimal('humidity_max', 5, 2)->nullable();
            $table->decimal('wind_min', 6, 2)->nullable();
            $table->decimal('wind_avg', 6, 2)->nullable();
            $table->decimal('wind_max', 6, 2)->nullable();
            $table->json('extra_stats')->nullable();
            $table->unsignedInteger('sample_count')->default(0);
            $table->timestamps();
            $table->unique(['device_id', 'bucket_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('environmental_rollups');
        Schema::dropIfExists('environmental_readings');
    }
};
