<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('zone_type');
            $table->boolean('requires_authorization')->default(false);
            $table->unsignedInteger('occupancy_limit')->nullable();
            $table->decimal('map_x', 8, 2)->nullable();
            $table->decimal('map_y', 8, 2)->nullable();
            $table->decimal('map_radius', 8, 2)->nullable();
            $table->string('color')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['zone_type', 'is_active']);
        });

        Schema::create('reader_zone_bindings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained()->restrictOnDelete();
            $table->timestamp('bound_from');
            $table->timestamp('bound_until')->nullable();
            $table->foreignId('bound_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['device_id', 'bound_until']);
            $table->index(['device_id', 'bound_from', 'bound_until']);
        });

        Schema::create('zone_access_lists', function (Blueprint $table) {
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
    }
};
