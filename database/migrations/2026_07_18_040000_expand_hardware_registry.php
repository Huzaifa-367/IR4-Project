<?php

use App\Enums\HardwareStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
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

        Schema::create('cameras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('reference')->unique();
            $table->string('camera_type');
            $table->foreignId('processed_by_device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->string('stream_url');
            $table->boolean('ai_enabled')->default(true);
            $table->string('status')->default('offline');
            $table->timestamp('last_frame_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['status']);
            $table->index(['asset_id']);
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->foreignId('asset_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->string('serial_number')->nullable()->unique()->after('reference');
            $table->timestamp('token_issued_at')->nullable()->after('api_token_hash');
            $table->json('config')->nullable()->after('token_issued_at');
        });

        // Map stub DeviceStatus `active` → HardwareStatus `online`
        DB::table('devices')->where('status', 'active')->update(['status' => HardwareStatus::Online->value]);
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('asset_id');
            $table->dropColumn(['serial_number', 'token_issued_at', 'config']);
        });

        Schema::dropIfExists('cameras');
        Schema::dropIfExists('assets');
    }
};
