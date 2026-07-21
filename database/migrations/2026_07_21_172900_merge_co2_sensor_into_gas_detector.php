<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fold legacy co2_sensor devices into gas_detector — CO₂ is a gas channel, not a device class.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('devices')
            ->where('device_type', 'co2_sensor')
            ->update(['device_type' => 'gas_detector']);
    }

    public function down(): void
    {
        // Irreversible: former co2_sensor rows cannot be distinguished after merge.
    }
};
