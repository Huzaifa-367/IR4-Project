<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DOC-23 linking: ingest device FK on roi_violations; enforce 1:1 camera↔AI device.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roi_violations', function (Blueprint $table): void {
            if (! Schema::hasColumn('roi_violations', 'device_id')) {
                $table->foreignId('device_id')
                    ->nullable()
                    ->after('camera_id')
                    ->constrained('devices')
                    ->nullOnDelete();
            }
        });

        // One AI device processes at most one camera (DOC-23 1:1). Multiple NULLs allowed.
        Schema::table('cameras', function (Blueprint $table): void {
            $table->unique('processed_by_device_id');
        });
    }

    public function down(): void
    {
        Schema::table('cameras', function (Blueprint $table): void {
            $table->dropUnique(['processed_by_device_id']);
        });

        Schema::table('roi_violations', function (Blueprint $table): void {
            if (Schema::hasColumn('roi_violations', 'device_id')) {
                $table->dropConstrainedForeignId('device_id');
            }
        });
    }
};
