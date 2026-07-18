<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_violations', function (Blueprint $table) {
            $table->id();
            $table->timestamp('observed_at');
            $table->string('vehicle_description');
            $table->string('violation_type');
            $table->text('description')->nullable();
            $table->text('action_taken');
            $table->foreignId('camera_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('logged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('observed_at');
        });

        Schema::create('weekly_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_number')->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('draft');
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('pdf_path')->nullable();
            $table->string('csv_path')->nullable();
            $table->json('data');
            $table->foreignId('supersedes_report_id')->nullable()->constrained('weekly_reports')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['period_start', 'period_end']);
            $table->index('status');
        });

        Schema::create('notifications', function (Blueprint $table) {
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
    }
};
