<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->string('api_url')->nullable()->after('config');
            $table->string('camera_ref')->nullable()->after('api_url');
            $table->string('printer_host')->nullable()->after('camera_ref');
            $table->unsignedSmallInteger('printer_port')->nullable()->after('printer_host');
        });

        if (Schema::hasColumn('devices', 'config')) {
            DB::table('devices')->orderBy('id')->each(function (object $row): void {
                $config = is_string($row->config) ? json_decode($row->config, true) : null;
                if (! is_array($config)) {
                    return;
                }

                $updates = [];
                if (($config['api_url'] ?? null) !== null && $row->api_url === null) {
                    $updates['api_url'] = $config['api_url'];
                }
                if (($config['camera_ref'] ?? null) !== null && $row->camera_ref === null) {
                    $updates['camera_ref'] = $config['camera_ref'];
                }

                if ($updates !== []) {
                    DB::table('devices')->where('id', $row->id)->update($updates);
                }
            });
        }

        DB::table('devices')->where('device_type', 'edge_compute')->update(['device_type' => 'camera_ai']);
        DB::table('devices')->whereIn('device_type', ['wifi_gateway', 'rs485_interface'])->update(['device_type' => 'other']);
    }

    public function down(): void
    {
        DB::table('devices')->where('device_type', 'camera_ai')->update(['device_type' => 'edge_compute']);

        Schema::table('devices', function (Blueprint $table): void {
            $table->dropColumn(['api_url', 'camera_ref', 'printer_host', 'printer_port']);
        });
    }
};
