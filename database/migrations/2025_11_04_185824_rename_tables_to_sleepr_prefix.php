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
        $prefix = env('DB_TABLE_PREFIX', 'sleepr_');
        
        // Only rename if prefix is set and tables don't already have the prefix
        if ($prefix && !empty($prefix)) {
            $tables = [
                'users',
                'migrations',
                'password_reset_tokens',
                'sessions',
                'cache',
                'cache_locks',
                'jobs',
                'job_batches',
                'failed_jobs',
            ];

            foreach ($tables as $table) {
                $oldName = $table;
                $newName = $prefix . $table;
                
                // Check if old table exists and new table doesn't exist
                $oldExists = DB::selectOne("
                    SELECT EXISTS (
                        SELECT FROM information_schema.tables 
                        WHERE table_schema = 'public' 
                        AND table_name = ?
                    ) as exists", [$oldName])->exists ?? false;
                
                $newExists = DB::selectOne("
                    SELECT EXISTS (
                        SELECT FROM information_schema.tables 
                        WHERE table_schema = 'public' 
                        AND table_name = ?
                    ) as exists", [$newName])->exists ?? false;
                
                if ($oldExists && !$newExists) {
                    // Rename old table to new prefixed name
                    DB::statement("ALTER TABLE {$oldName} RENAME TO {$newName}");
                } elseif ($oldExists && $newExists) {
                    // Both exist - drop the old unprefixed one (assuming prefixed is the correct one)
                    DB::statement("DROP TABLE IF EXISTS {$oldName} CASCADE");
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $prefix = env('DB_TABLE_PREFIX', 'sleepr_');
        
        if ($prefix && !empty($prefix)) {
            $tables = [
                'users',
                'migrations',
                'password_reset_tokens',
                'sessions',
                'cache',
                'cache_locks',
                'jobs',
                'job_batches',
                'failed_jobs',
            ];

            foreach ($tables as $table) {
                $oldName = $prefix . $table;
                $newName = $table;
                
                $oldExists = DB::selectOne("
                    SELECT EXISTS (
                        SELECT FROM information_schema.tables 
                        WHERE table_schema = 'public' 
                        AND table_name = ?
                    ) as exists", [$oldName])->exists ?? false;
                
                $newExists = DB::selectOne("
                    SELECT EXISTS (
                        SELECT FROM information_schema.tables 
                        WHERE table_schema = 'public' 
                        AND table_name = ?
                    ) as exists", [$newName])->exists ?? false;
                
                if ($oldExists && !$newExists) {
                    DB::statement("ALTER TABLE {$oldName} RENAME TO {$newName}");
                }
            }
        }
    }
};
