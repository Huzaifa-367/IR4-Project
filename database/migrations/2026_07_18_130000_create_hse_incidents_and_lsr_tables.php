<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hse_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_number')->unique();
            $table->string('source')->default('manual');
            $table->foreignId('alert_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('camera_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->string('status')->default('open');
            $table->string('incident_type')->nullable();
            $table->string('severity')->nullable();
            $table->text('nature_of_incident')->nullable();
            $table->text('immediate_action')->nullable();
            $table->text('corrective_action')->nullable();
            $table->foreignId('classified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('classified_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->string('close_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('status');
            $table->index(['incident_type', 'severity']);
            $table->index('occurred_at');
        });

        Schema::create('incident_personnel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hse_incident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained();
            $table->string('involvement');
            $table->timestamps();
            $table->unique(['hse_incident_id', 'worker_id']);
        });

        Schema::create('incident_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hse_incident_id')->constrained()->cascadeOnDelete();
            $table->string('evidence_type');
            $table->string('file_path')->nullable();
            $table->json('payload')->nullable();
            $table->foreignId('ppe_violation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('camera_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('captured_at')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('lsr_violations', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->timestamp('occurred_at');
            $table->foreignId('worker_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('camera_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('alert_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ppe_violation_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->text('action_taken')->nullable();
            $table->string('status')->default('open');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('logged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['category', 'status']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lsr_violations');
        Schema::dropIfExists('incident_evidence');
        Schema::dropIfExists('incident_personnel');
        Schema::dropIfExists('hse_incidents');
    }
};
