<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('employee_code')->nullable()->unique();
            $table->string('badge_number')->nullable()->unique();
            $table->string('contractor');
            $table->string('role_title')->nullable();
            $table->string('worker_type');
            $table->string('phone')->nullable();
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('present')->default(false);
            $table->timestamp('last_seen_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['contractor']);
            $table->index(['worker_type']);
            $table->index(['is_active', 'present']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workers');
    }
};
