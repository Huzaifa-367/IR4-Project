<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ppe_violations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('camera_id')->constrained()->restrictOnDelete();
            $table->string('violation_type');
            $table->timestamp('detected_at')->index();
            $table->unsignedInteger('worker_count')->default(1);
            $table->string('snapshot_path');
            $table->decimal('confidence', 5, 2)->nullable();
            $table->string('location_label')->nullable();
            $table->foreignId('alert_id')->nullable()->constrained()->nullOnDelete();
            $table->string('review_status')->default('unreviewed');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note')->nullable();
            $table->boolean('is_backfill')->default(false);
            $table->uuid('event_uid');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['camera_id', 'event_uid']);
            $table->index(['violation_type', 'detected_at']);
            $table->index(['review_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ppe_violations');
    }
};
