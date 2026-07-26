<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('alert_type');
            $table->string('severity');
            $table->string('title');
            $table->json('payload')->nullable();
            $table->nullableMorphs('alertable');
            $table->string('status')->default('open');
            $table->timestamp('raised_at')->index();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->boolean('audible')->default(false);
            $table->string('dedupe_key')->nullable()->index();
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'severity']);
            $table->index(['alert_type', 'status']);
        });

        Schema::create('ingest_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('stream');
            $table->uuid('event_uid');
            $table->timestamp('recorded_at')->index();
            $table->timestamp('received_at')->index();
            $table->boolean('is_backfill')->default(false);
            $table->boolean('clock_skew')->default(false);
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->unique(['device_id', 'event_uid']);
            $table->index(['stream', 'recorded_at']);
        });

        Schema::create('rfid_tags', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tag_uid')->unique();
            $table->foreignId('worker_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('in_stock')->index();
            $table->timestamp('assigned_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['worker_id', 'status']);
        });

        Schema::create('worker_positions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tag_id')->constrained('rfid_tags')->cascadeOnDelete()->unique();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('last_seen_at');
            $table->boolean('is_on_site')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('tag_readings', function (Blueprint $table): void {
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

        Schema::create('entry_exit_logs', function (Blueprint $table): void {
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

        Schema::create('portable_devices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
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

        Schema::create('evacuation_reports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('status')->default('open')->index();
            $table->timestamp('triggered_at');
            $table->foreignId('triggered_by')->constrained('users');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('force_closed')->default(false);
            $table->string('close_note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('evacuation_report_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
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

        Schema::create('ppe_violations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('camera_id')->constrained()->restrictOnDelete();
            $table->string('violation_type');
            $table->timestamp('detected_at')->index();
            $table->unsignedInteger('worker_count')->default(1);
            $table->string('snapshot_path');
            $table->decimal('confidence', 5, 2)->nullable();
            $table->string('location_label')->nullable();
            $table->foreignId('alert_id')->nullable()->constrained()->nullOnDelete();
            $table->string('review_status')->default('unreviewed')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note')->nullable();
            $table->boolean('is_backfill')->default(false);
            $table->uuid('event_uid');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['camera_id', 'event_uid']);
            $table->index(['violation_type', 'detected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ppe_violations');
        Schema::dropIfExists('evacuation_report_entries');
        Schema::dropIfExists('evacuation_reports');
        Schema::dropIfExists('portable_devices');
        Schema::dropIfExists('entry_exit_logs');
        Schema::dropIfExists('tag_readings');
        Schema::dropIfExists('worker_positions');
        Schema::dropIfExists('rfid_tags');
        Schema::dropIfExists('ingest_events');
        Schema::dropIfExists('alerts');
    }
};
