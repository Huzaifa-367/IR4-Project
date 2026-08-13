<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tag_readings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('antenna')->nullable()->after('rssi');
        });
    }

    public function down(): void
    {
        Schema::table('tag_readings', function (Blueprint $table): void {
            $table->dropColumn('antenna');
        });
    }
};
