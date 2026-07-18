<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->string('alert_type');
            $table->string('severity');
            $table->string('title');
            $table->json('payload')->nullable();
            $table->nullableMorphs('alertable');
            $table->string('status')->default('open');
            $table->timestamp('raised_at')->index();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->boolean('audible')->default(false);
            $table->string('dedupe_key')->nullable()->index();
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'severity']);
            $table->index(['alert_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
