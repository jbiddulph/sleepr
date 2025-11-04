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
        $oldPrefix = 'sleepr_';
        $newPrefix = 'sleeper_';
        
        // Get all tables with the old prefix
        $tables = DB::select("
            SELECT table_name 
            FROM information_schema.tables 
            WHERE table_schema = 'public' 
            AND table_name LIKE ?
            ORDER BY table_name
        ", [$oldPrefix . '%']);

        foreach ($tables as $table) {
            $oldName = $table->table_name;
            $tableName = str_replace($oldPrefix, '', $oldName);
            $newName = $newPrefix . $tableName;
            
            // Check if new table already exists
            $newExists = DB::selectOne("
                SELECT EXISTS (
                    SELECT FROM information_schema.tables 
                    WHERE table_schema = 'public' 
                    AND table_name = ?
                ) as exists
            ", [$newName])->exists ?? false;
            
            if (!$newExists) {
                DB::statement("ALTER TABLE {$oldName} RENAME TO {$newName}");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $oldPrefix = 'sleeper_';
        $newPrefix = 'sleepr_';
        
        // Get all tables with the old prefix
        $tables = DB::select("
            SELECT table_name 
            FROM information_schema.tables 
            WHERE table_schema = 'public' 
            AND table_name LIKE ?
            ORDER BY table_name
        ", [$oldPrefix . '%']);

        foreach ($tables as $table) {
            $oldName = $table->table_name;
            $tableName = str_replace($oldPrefix, '', $oldName);
            $newName = $newPrefix . $tableName;
            
            // Check if new table already exists
            $newExists = DB::selectOne("
                SELECT EXISTS (
                    SELECT FROM information_schema.tables 
                    WHERE table_schema = 'public' 
                    AND table_name = ?
                ) as exists
            ", [$newName])->exists ?? false;
            
            if (!$newExists) {
                DB::statement("ALTER TABLE {$oldName} RENAME TO {$newName}");
            }
        }
    }
};
