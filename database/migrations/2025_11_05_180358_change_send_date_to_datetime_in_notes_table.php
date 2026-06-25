<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $tableName = DB::getTablePrefix() . 'notes';
        DB::statement("ALTER TABLE {$tableName} ALTER COLUMN send_date TYPE timestamp USING send_date::timestamp");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $tableName = DB::getTablePrefix() . 'notes';
        DB::statement("ALTER TABLE {$tableName} ALTER COLUMN send_date TYPE date USING send_date::date");
    }
};
