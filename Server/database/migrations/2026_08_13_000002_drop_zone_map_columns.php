<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table): void {
            $table->dropColumn(['latitude', 'longitude', 'radius_meters']);
        });
    }

    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table): void {
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('radius_meters', 9, 2)->nullable();
        });
    }
};
