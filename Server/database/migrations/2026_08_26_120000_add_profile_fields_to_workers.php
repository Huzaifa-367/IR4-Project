<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workers', function (Blueprint $table): void {
            $table->string('nationality')->nullable()->after('role_title');
            $table->date('date_of_birth')->nullable()->after('nationality');
            $table->date('joined_on')->nullable()->after('date_of_birth');
            $table->string('government_id_number')->nullable()->after('joined_on');
        });
    }

    public function down(): void
    {
        Schema::table('workers', function (Blueprint $table): void {
            $table->dropColumn([
                'nationality',
                'date_of_birth',
                'joined_on',
                'government_id_number',
            ]);
        });
    }
};
