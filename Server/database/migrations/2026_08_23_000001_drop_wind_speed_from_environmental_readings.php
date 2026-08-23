<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('environmental_readings', function (Blueprint $table) {
            $table->dropColumn('wind_speed_ms');
        });
    }

    public function down(): void
    {
        Schema::table('environmental_readings', function (Blueprint $table) {
            $table->decimal('wind_speed_ms', 6, 2)->nullable()->after('humidity_pct');
        });
    }
};
