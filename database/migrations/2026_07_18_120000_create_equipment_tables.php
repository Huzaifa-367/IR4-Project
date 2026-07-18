<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->string('equipment_code')->unique();
            $table->uuid('qr_token')->unique();
            $table->string('name');
            $table->string('equipment_type');
            $table->string('status')->default('in_service');
            $table->boolean('is_checkoutable')->default(false);
            $table->string('location_label')->nullable();
            $table->text('description')->nullable();
            $table->date('next_inspection_due')->nullable();
            $table->date('next_service_due')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('status');
            $table->index('next_inspection_due');
            $table->index('next_service_due');
        });

        Schema::create('equipment_inspections', function (Blueprint $table) {
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

        Schema::create('equipment_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained()->cascadeOnDelete();
            $table->date('performed_at');
            $table->string('maintenance_type');
            $table->text('description');
            $table->string('performed_by_name')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('next_due')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['equipment_id', 'performed_at']);
        });

        Schema::create('maintenance_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained()->cascadeOnDelete();
            $table->string('schedule_type');
            $table->unsignedInteger('interval_days');
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['equipment_id', 'schedule_type']);
        });

        Schema::create('equipment_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('file_path');
            $table->string('mime');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('equipment_checkouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->timestamp('checked_out_at');
            $table->foreignId('checked_out_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->timestamp('expected_return_at')->nullable();
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
            $table->index('expected_return_at');
        });

        Schema::create('equipment_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('status')->default('pending');
            $table->json('summary')->nullable();
            $table->timestamps();
            $table->index(['created_by', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_imports');
        Schema::dropIfExists('equipment_checkouts');
        Schema::dropIfExists('equipment_documents');
        Schema::dropIfExists('maintenance_schedules');
        Schema::dropIfExists('equipment_maintenances');
        Schema::dropIfExists('equipment_inspections');
        Schema::dropIfExists('equipment');
    }
};
