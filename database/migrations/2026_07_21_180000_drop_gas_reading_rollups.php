<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('gas_reading_rollups');
    }

    public function down(): void
    {
        // Intentionally empty — gas history is raw-only; do not recreate rollups.
    }
};
