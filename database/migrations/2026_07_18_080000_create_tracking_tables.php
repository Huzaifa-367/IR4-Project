<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfid_tags', function (Blueprint $table) {
            $table->id();
            $table->string('tag_uid')->unique();
            $table->foreignId('worker_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('in_stock');
            $table->timestamp('assigned_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status']);
            $table->index(['worker_id', 'status']);
        });

        Schema::create('worker_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->unique()->constrained('rfid_tags')->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('last_seen_at');
            $table->boolean('is_on_site')->default(false);
            $table->timestamps();
            $table->index(['zone_id']);
            $table->index(['is_on_site']);
        });

        Schema::create('tag_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained('rfid_tags')->cascadeOnDelete();
            $table->foreignId('reader_device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('recorded_at')->index();
            $table->timestamp('received_at');
            $table->integer('rssi')->nullable();
            $table->boolean('is_backfill')->default(false);
            $table->boolean('clock_skew')->default(false);
            $table->uuid('event_uid');
            $table->timestamps();
            $table->unique(['reader_device_id', 'event_uid']);
            $table->index(['tag_id', 'recorded_at']);
            $table->index(['zone_id', 'recorded_at']);
        });

        Schema::create('entry_exit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->nullable()->constrained('rfid_tags')->nullOnDelete();
            $table->foreignId('gate_zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->string('direction');
            $table->timestamp('occurred_at')->index();
            $table->string('source')->default('gate_reader');
            $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('correction_note')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['worker_id', 'occurred_at']);
        });

        Schema::create('portable_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->string('device_type');
            $table->string('make_model')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('approval_reference')->nullable();
            $table->string('status')->default('approved');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoke_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['worker_id', 'status']);
        });

        Schema::create('evacuation_reports', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('open');
            $table->timestamp('triggered_at');
            $table->foreignId('triggered_by')->constrained('users');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('force_closed')->default(false);
            $table->string('close_note')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status']);
        });

        Schema::create('evacuation_report_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evacuation_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained();
            $table->foreignId('last_zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('muster_status')->default('unaccounted');
            $table->timestamp('accounted_at')->nullable();
            $table->string('accounted_source')->nullable();
            $table->foreignId('accounted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['evacuation_report_id', 'worker_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evacuation_report_entries');
        Schema::dropIfExists('evacuation_reports');
        Schema::dropIfExists('portable_devices');
        Schema::dropIfExists('entry_exit_logs');
        Schema::dropIfExists('tag_readings');
        Schema::dropIfExists('worker_positions');
        Schema::dropIfExists('rfid_tags');
    }
};
