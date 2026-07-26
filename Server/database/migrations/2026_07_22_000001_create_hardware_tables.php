<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('asset_type');
            $table->string('name');
            $table->string('identifier')->unique();
            $table->string('status')->default('active');
            $table->boolean('is_mobile')->default(false);
            $table->string('current_location_label')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['asset_type', 'status']);
        });

        Schema::create('devices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('asset_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('reference')->unique();
            $table->string('serial_number')->nullable()->unique();
            $table->string('device_type')->default('other');
            $table->string('status')->default('online')->index();
            $table->string('api_token_hash', 64)->nullable()->unique();
            $table->timestamp('token_issued_at')->nullable();
            $table->json('config')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cameras', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('reference')->unique();
            $table->string('camera_type');
            $table->foreignId('processed_by_device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->string('stream_url');
            $table->boolean('ai_enabled')->default(true);
            $table->string('status')->default('offline')->index();
            $table->timestamp('last_frame_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cameras');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('assets');
    }
};
