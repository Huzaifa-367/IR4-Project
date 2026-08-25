<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roi_violations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('camera_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->foreignId('camera_roi_id')->nullable()->constrained('camera_rois')->nullOnDelete();
            $table->string('roi_reference');
            $table->string('event_type');
            $table->timestamp('detected_at');
            $table->decimal('confidence', 5, 2)->nullable();
            $table->string('snapshot_path')->nullable();
            $table->string('location_label')->nullable();
            $table->foreignId('alert_id')->nullable()->constrained('alerts')->nullOnDelete();
            $table->string('review_status')->default('unreviewed');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->boolean('is_backfill')->default(false);
            $table->uuid('event_uid');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['camera_id', 'event_uid']);
            $table->index(['event_type', 'detected_at']);
            $table->index(['review_status', 'detected_at']);
            $table->index(['roi_reference', 'detected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roi_violations');
    }
};
