<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('equipment_code')->unique();
            $table->uuid('qr_token')->unique();
            $table->string('name');
            $table->string('equipment_type');
            $table->string('status')->default('in_service')->index();
            $table->boolean('is_checkoutable')->default(false);
            $table->string('location_label')->nullable();
            $table->text('description')->nullable();
            $table->date('next_inspection_due')->nullable()->index();
            $table->date('next_service_due')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('equipment_inspections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('equipment_id')->constrained()->cascadeOnDelete();
            $table->date('inspected_at');
            $table->string('outcome');
            $table->text('notes')->nullable();
            $table->foreignId('inspector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('next_due')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['equipment_id', 'inspected_at']);
        });

        Schema::create('equipment_maintenances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('equipment_id')->constrained()->cascadeOnDelete();
            $table->date('performed_at');
            $table->string('maintenance_type');
            $table->text('description');
            $table->string('performed_by_name')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('next_due')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['equipment_id', 'performed_at']);
        });

        Schema::create('maintenance_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('equipment_id')->constrained()->cascadeOnDelete();
            $table->string('schedule_type');
            $table->unsignedInteger('interval_days');
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['equipment_id', 'schedule_type']);
        });

        Schema::create('equipment_documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('equipment_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('file_path');
            $table->string('mime');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('equipment_checkouts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('equipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->timestamp('checked_out_at');
            $table->foreignId('checked_out_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('expected_return_at')->nullable()->index();
            $table->timestamp('returned_at')->nullable();
            $table->foreignId('returned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('condition_out')->nullable();
            $table->string('condition_in')->nullable();
            $table->string('return_status')->nullable();
            $table->string('return_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['equipment_id', 'returned_at']);
            $table->index(['worker_id', 'returned_at']);
        });

        Schema::create('equipment_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('status')->default('pending');
            $table->json('summary')->nullable();
            $table->timestamps();
            $table->index(['created_by', 'created_at']);
        });

        Schema::create('hse_incidents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('incident_number')->unique();
            $table->string('source')->default('manual');
            $table->foreignId('alert_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('camera_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('occurred_at')->index();
            $table->string('status')->default('open')->index();
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
            $table->index(['incident_type', 'severity']);
        });

        Schema::create('incident_personnel', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hse_incident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained();
            $table->string('involvement');
            $table->timestamps();
            $table->unique(['hse_incident_id', 'worker_id']);
        });

        Schema::create('incident_evidence', function (Blueprint $table): void {
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

        Schema::create('lsr_violations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('category');
            $table->timestamp('occurred_at')->index();
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
        });

        Schema::create('vehicle_violations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->timestamp('observed_at')->index();
            $table->string('vehicle_description');
            $table->string('violation_type');
            $table->text('description')->nullable();
            $table->text('action_taken');
            $table->foreignId('camera_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('logged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('weekly_reports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('report_number')->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('draft')->index();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('pdf_path')->nullable();
            $table->string('csv_path')->nullable();
            $table->json('data');
            $table->foreignId('supersedes_report_id')->nullable()->constrained('weekly_reports')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['period_start', 'period_end']);
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('weekly_reports');
        Schema::dropIfExists('vehicle_violations');
        Schema::dropIfExists('lsr_violations');
        Schema::dropIfExists('incident_evidence');
        Schema::dropIfExists('incident_personnel');
        Schema::dropIfExists('hse_incidents');
        Schema::dropIfExists('equipment_imports');
        Schema::dropIfExists('equipment_checkouts');
        Schema::dropIfExists('equipment_documents');
        Schema::dropIfExists('maintenance_schedules');
        Schema::dropIfExists('equipment_maintenances');
        Schema::dropIfExists('equipment_inspections');
        Schema::dropIfExists('equipment');
    }
};
