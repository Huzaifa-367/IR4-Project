<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cameras', function (Blueprint $table): void {
            $table->unsignedInteger('ptz_generation')->default(0)->after('meta');
        });

        Schema::create('camera_roi_sets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('camera_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('draft');
            $table->string('view_fingerprint');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('stale_at')->nullable();
            $table->string('stale_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique('camera_id');
        });

        Schema::create('camera_rois', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('camera_roi_set_id')->constrained()->cascadeOnDelete();
            $table->foreignId('camera_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('reference');
            $table->json('polygon');
            $table->string('color', 16)->default('#22d3ee');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->json('meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['camera_id', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('camera_rois');
        Schema::dropIfExists('camera_roi_sets');
        Schema::table('cameras', function (Blueprint $table): void {
            $table->dropColumn('ptz_generation');
        });
    }
};
