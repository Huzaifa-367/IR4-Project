<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE ppe_violations MODIFY snapshot_path VARCHAR(255) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE ppe_violations MODIFY snapshot_path VARCHAR(255) NOT NULL');
    }
};
