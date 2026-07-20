<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issuer/receiver/gas-tester capability is RBAC-only (DOC-22 confirmed).
 * Worker competence evidence lives on worker_documents — not user_certifications.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('user_certifications');
    }

    public function down(): void
    {
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
    }
};
