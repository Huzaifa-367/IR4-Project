<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('camera_headcount_readings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('camera_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('recorded_at')->index();
            $table->timestamp('received_at');
            $table->unsignedInteger('count');
            $table->boolean('is_backfill')->default(false);
            $table->boolean('clock_skew')->default(false);
            $table->uuid('event_uid');
            $table->timestamps();

            $table->unique(['device_id', 'event_uid']);
            $table->index(['device_id', 'recorded_at']);
            $table->index(['zone_id', 'recorded_at']);
            $table->index(['camera_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('camera_headcount_readings');
    }
};
