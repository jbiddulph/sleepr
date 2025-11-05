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
        // Populate recipients field for existing notes that don't have it
        // Note: DB::table() automatically applies the table prefix
        $notes = DB::table('notes')
            ->whereNull('recipients')
            ->orWhere('recipients', '')
            ->get(['id']);

        foreach ($notes as $note) {
            $recipientEmails = DB::table('note_recipients')
                ->where('note_id', $note->id)
                ->pluck('email')
                ->toArray();

            if (!empty($recipientEmails)) {
                DB::table('notes')
                    ->where('id', $note->id)
                    ->update(['recipients' => implode(', ', $recipientEmails)]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration only populates data, no schema changes to reverse
    }
};
