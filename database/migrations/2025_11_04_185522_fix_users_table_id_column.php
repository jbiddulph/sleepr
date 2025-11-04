<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get the table name with prefix
        $tableName = DB::getTablePrefix() . 'users';
        
        // Check if the id column is UUID type (only needed for old tables)
        $columnType = DB::selectOne("
            SELECT data_type 
            FROM information_schema.columns 
            WHERE table_schema = 'public' 
            AND table_name = ? 
            AND column_name = 'id'
        ", [$tableName]);
        
        // Only add UUID default if the column is actually UUID type
        if ($columnType && $columnType->data_type === 'uuid') {
            DB::statement("ALTER TABLE {$tableName} ALTER COLUMN id SET DEFAULT gen_random_uuid()");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Get the table name with prefix
        $tableName = DB::getTablePrefix() . 'users';
        
        // Remove default UUID generation
        DB::statement("ALTER TABLE {$tableName} ALTER COLUMN id DROP DEFAULT");
    }
};
