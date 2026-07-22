<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('employee_code')->nullable()->unique();
            $table->string('badge_number')->nullable()->unique();
            $table->string('contractor')->index();
            $table->string('role_title')->nullable();
            $table->string('worker_type')->index();
            $table->string('phone')->nullable();
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('present')->default(false);
            $table->timestamp('last_seen_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['is_active', 'present']);
        });

        Schema::create('worker_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('status')->default('pending');
            $table->json('summary')->nullable();
            $table->timestamps();
            $table->index(['created_by', 'created_at']);
        });

        Schema::create('zones', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('zone_type');
            $table->boolean('requires_authorization')->default(false);
            $table->boolean('requires_permit')->default(false);
            $table->unsignedInteger('occupancy_limit')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('radius_meters', 9, 2)->nullable();
            $table->string('color')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['zone_type', 'is_active']);
        });

        Schema::create('reader_zone_bindings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained()->restrictOnDelete();
            $table->timestamp('bound_from');
            $table->timestamp('bound_until')->nullable();
            $table->foreignId('bound_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['device_id', 'bound_until']);
            $table->index(['device_id', 'bound_from', 'bound_until']);
        });

        Schema::create('zone_access_lists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('zone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamps();
            $table->unique(['zone_id', 'worker_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zone_access_lists');
        Schema::dropIfExists('reader_zone_bindings');
        Schema::dropIfExists('zones');
        Schema::dropIfExists('worker_imports');
        Schema::dropIfExists('workers');
    }
};
