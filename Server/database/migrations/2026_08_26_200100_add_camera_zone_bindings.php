<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('camera_zone_bindings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('camera_id')->constrained()->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained()->restrictOnDelete();
            $table->timestamp('bound_from');
            $table->timestamp('bound_until')->nullable();
            $table->foreignId('bound_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['camera_id', 'bound_until']);
            $table->index(['camera_id', 'bound_from', 'bound_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('camera_zone_bindings');
    }
};
