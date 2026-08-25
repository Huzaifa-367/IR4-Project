<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Merge cameras + linked camera_ai devices into a single devices row (device_type=camera).
 * Repoint camera_id FKs to the unified device id, then drop cameras.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const CAMERA_FK_TABLES = [
        'camera_roi_sets',
        'camera_rois',
        'roi_violations',
        'ppe_violations',
        'hse_incidents',
        'lsr_violations',
        'incident_evidence',
        'vehicle_violations',
    ];

    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->string('camera_type')->nullable()->after('device_type');
            $table->string('stream_url')->nullable()->after('camera_type');
            $table->boolean('ai_enabled')->default(true)->after('stream_url');
            $table->timestamp('last_frame_at')->nullable()->after('last_seen_at');
            $table->unsignedInteger('ptz_generation')->default(0)->after('last_frame_at');
            $table->json('meta')->nullable()->after('ptz_generation');
        });

        if (! Schema::hasTable('cameras')) {
            DB::table('devices')->where('device_type', 'camera_ai')->update(['device_type' => 'camera']);
            $this->dropCameraRefColumn();

            return;
        }

        /** @var array<int, int> $idMap old cameras.id => unified devices.id */
        $idMap = [];

        foreach (DB::table('cameras')->orderBy('id')->get() as $cam) {
            $aiDeviceId = $cam->processed_by_device_id !== null ? (int) $cam->processed_by_device_id : null;

            if ($aiDeviceId !== null) {
                DB::table('devices')->where('id', $aiDeviceId)->update([
                    'asset_id' => $cam->asset_id,
                    'name' => $cam->name,
                    'reference' => $cam->reference,
                    'device_type' => 'camera',
                    'camera_type' => $cam->camera_type,
                    'stream_url' => $cam->stream_url,
                    'ai_enabled' => (bool) $cam->ai_enabled,
                    'status' => $cam->status,
                    'last_frame_at' => $cam->last_frame_at,
                    'ptz_generation' => (int) ($cam->ptz_generation ?? 0),
                    'meta' => $cam->meta,
                    'updated_at' => now(),
                ]);
                $idMap[(int) $cam->id] = $aiDeviceId;
            } else {
                $newId = (int) DB::table('devices')->insertGetId([
                    'uuid' => $cam->uuid,
                    'asset_id' => $cam->asset_id,
                    'name' => $cam->name,
                    'reference' => $cam->reference,
                    'serial_number' => null,
                    'device_type' => 'camera',
                    'camera_type' => $cam->camera_type,
                    'stream_url' => $cam->stream_url,
                    'ai_enabled' => (bool) $cam->ai_enabled,
                    'status' => $cam->status,
                    'last_frame_at' => $cam->last_frame_at,
                    'ptz_generation' => (int) ($cam->ptz_generation ?? 0),
                    'meta' => $cam->meta,
                    'created_at' => $cam->created_at,
                    'updated_at' => $cam->updated_at,
                ]);
                $idMap[(int) $cam->id] = $newId;
            }
        }

        $mergedDeviceIds = array_values($idMap);

        foreach (self::CAMERA_FK_TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'camera_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropForeign(['camera_id']);
            });

            foreach ($idMap as $oldCameraId => $deviceId) {
                if ($oldCameraId === $deviceId) {
                    continue;
                }

                DB::table($table)->where('camera_id', $oldCameraId)->update(['camera_id' => $deviceId]);
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $foreign = $blueprint->foreign('camera_id')->references('id')->on('devices');
                match ($table) {
                    'camera_roi_sets', 'camera_rois', 'roi_violations' => $foreign->cascadeOnDelete(),
                    'ppe_violations' => $foreign->restrictOnDelete(),
                    default => $foreign->nullOnDelete(),
                };
            });
        }

        if (Schema::hasTable('roi_violations') && Schema::hasColumn('roi_violations', 'device_id')) {
            DB::table('roi_violations')
                ->whereNotNull('camera_id')
                ->update(['device_id' => DB::raw('camera_id')]);
        }

        DB::table('devices')
            ->where('device_type', 'camera_ai')
            ->when($mergedDeviceIds !== [], fn ($q) => $q->whereNotIn('id', $mergedDeviceIds))
            ->delete();

        Schema::drop('cameras');

        $this->dropCameraRefColumn();
    }

    public function down(): void
    {
        throw new \RuntimeException('merge_cameras_into_devices is not reversible.');
    }

    private function dropCameraRefColumn(): void
    {
        if (Schema::hasColumn('devices', 'camera_ref')) {
            Schema::table('devices', function (Blueprint $table): void {
                $table->dropColumn('camera_ref');
            });
        }
    }
};
