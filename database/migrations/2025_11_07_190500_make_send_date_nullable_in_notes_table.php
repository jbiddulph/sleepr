<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = DB::getTablePrefix() . 'notes';
        DB::statement("ALTER TABLE {$tableName} ALTER COLUMN send_date DROP NOT NULL");
    }

    public function down(): void
    {
        $tableName = DB::getTablePrefix() . 'notes';
        DB::statement("UPDATE {$tableName} SET send_date = NOW() WHERE send_date IS NULL");
        DB::statement("ALTER TABLE {$tableName} ALTER COLUMN send_date SET NOT NULL");
    }
};

