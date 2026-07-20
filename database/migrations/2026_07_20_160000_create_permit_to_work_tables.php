<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->boolean('requires_permit')->default(false)->after('requires_authorization');
        });

        Schema::create('worker_document_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->default('competence');
            $table->boolean('requires_expiry')->default(true);
            $table->boolean('requires_file')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('worker_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained('workers')->cascadeOnDelete();
            $table->foreignId('worker_document_type_id')->constrained('worker_document_types')->restrictOnDelete();
            $table->string('document_number')->nullable();
            $table->string('issuing_body')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('file_path')->nullable();
            $table->string('verification_status')->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['worker_id', 'worker_document_type_id']);
            $table->index(['expires_at']);
        });

        Schema::create('user_certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('cert_type');
            $table->string('certificate_number')->nullable();
            $table->string('issuing_body')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('file_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'cert_type', 'expires_at']);
        });

        Schema::create('permit_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('colour_token')->nullable();
            $table->string('sa_form_code')->nullable();
            $table->boolean('requires_gas_test')->default(true);
            $table->boolean('requires_approver')->default(false);
            $table->boolean('requires_joint_inspection')->default(true);
            $table->unsignedInteger('default_validity_minutes')->default(480);
            $table->unsignedTinyInteger('max_renewals')->default(1);
            $table->unsignedInteger('max_total_minutes')->default(1440);
            $table->boolean('allows_extended')->default(false);
            $table->unsignedInteger('retest_interval_minutes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('permit_type_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permit_type_id')->constrained('permit_types')->cascadeOnDelete();
            $table->string('code');
            $table->string('label');
            $table->boolean('is_mandatory')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['permit_type_id', 'code']);
        });

        Schema::create('permit_type_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permit_type_id')->constrained('permit_types')->cascadeOnDelete();
            $table->string('role_code');
            $table->string('label');
            $table->unsignedTinyInteger('min_count')->default(1);
            $table->boolean('is_mandatory')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['permit_type_id', 'role_code']);
        });

        Schema::create('permit_type_gas_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permit_type_id')->constrained('permit_types')->cascadeOnDelete();
            $table->string('channel_code');
            $table->string('label');
            $table->string('unit')->nullable();
            $table->decimal('warn_below', 10, 3)->nullable();
            $table->decimal('warn_above', 10, 3)->nullable();
            $table->decimal('alarm_below', 10, 3)->nullable();
            $table->decimal('alarm_above', 10, 3)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['permit_type_id', 'channel_code']);
        });

        Schema::create('permit_type_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permit_type_id')->constrained('permit_types')->cascadeOnDelete();
            $table->foreignId('conflicts_with_type_id')->constrained('permit_types')->cascadeOnDelete();
            $table->string('scope')->default('same_zone');
            $table->string('severity')->default('warn');
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['permit_type_id', 'conflicts_with_type_id', 'scope'], 'permit_type_conflicts_unique');
        });

        Schema::create('permit_type_document_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permit_type_id')->constrained('permit_types')->cascadeOnDelete();
            $table->foreignId('worker_document_type_id')
                ->constrained('worker_document_types', 'id', 'pt_doc_req_wdoc_type_fk')
                ->restrictOnDelete();
            $table->string('role_code')->nullable();
            $table->boolean('is_mandatory')->default(true);
            $table->boolean('must_be_verified')->default(true);
            $table->timestamps();
            $table->index(['permit_type_id', 'role_code']);
        });

        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->text('description')->nullable();
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->string('status')->default('open');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('permits', function (Blueprint $table) {
            $table->id();
            $table->string('permit_number')->unique();
            $table->foreignId('permit_type_id')->constrained('permit_types')->restrictOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->text('task_description');
            $table->foreignId('receiver_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('issuer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->unsignedTinyInteger('renewal_count')->default(0);
            $table->boolean('is_extended')->default(false);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->json('checklist')->nullable();
            $table->json('controls')->nullable();
            $table->boolean('gas_test_required')->default(true);
            $table->timestamp('joint_inspection_at')->nullable();
            $table->foreignId('joint_inspection_by_issuer')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('joint_inspection_by_receiver')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('close_note')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->string('source')->default('ir4');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status']);
            $table->index(['permit_type_id', 'status']);
            $table->index(['zone_id', 'status']);
            $table->index(['valid_from', 'valid_to']);
        });

        Schema::create('permit_gas_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permit_id')->constrained('permits')->cascadeOnDelete();
            $table->timestamp('tested_at');
            $table->json('readings');
            $table->string('result');
            $table->string('source');
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->foreignId('tested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('phase')->default('pre_start');
            $table->timestamps();
            $table->index(['permit_id', 'tested_at']);
        });

        Schema::create('permit_personnel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permit_id')->constrained('permits')->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('workers')->restrictOnDelete();
            $table->string('role_code');
            $table->timestamp('documents_verified_at')->nullable();
            $table->timestamps();
            $table->unique(['permit_id', 'worker_id', 'role_code']);
        });

        Schema::create('permit_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permit_id')->constrained('permits')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('action');
            $table->text('note')->nullable();
            $table->timestamp('signed_at');
            $table->timestamps();
            $table->index(['permit_id', 'action']);
        });

        Schema::create('permit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permit_id')->constrained('permits')->cascadeOnDelete();
            $table->string('event');
            $table->json('payload')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['permit_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permit_events');
        Schema::dropIfExists('permit_approvals');
        Schema::dropIfExists('permit_personnel');
        Schema::dropIfExists('permit_gas_tests');
        Schema::dropIfExists('permits');
        Schema::dropIfExists('work_orders');
        Schema::dropIfExists('permit_type_document_requirements');
        Schema::dropIfExists('permit_type_conflicts');
        Schema::dropIfExists('permit_type_gas_channels');
        Schema::dropIfExists('permit_type_roles');
        Schema::dropIfExists('permit_type_checklist_items');
        Schema::dropIfExists('permit_types');
        Schema::dropIfExists('user_certifications');
        Schema::dropIfExists('worker_documents');
        Schema::dropIfExists('worker_document_types');

        Schema::table('zones', function (Blueprint $table) {
            $table->dropColumn('requires_permit');
        });
    }
};
