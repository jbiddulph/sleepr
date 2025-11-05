<?php

namespace App\Console\Commands;

use App\Jobs\SendNoteEmail;
use App\Models\NoteRecipient;
use Illuminate\Console\Command;

class SendDueNotes extends Command
{
    protected $signature = 'notes:send-due';
    protected $description = 'Dispatch jobs to send due note emails';

    public function handle(): int
    {
        $due = NoteRecipient::query()
            ->whereNull('sent_at')
            ->whereNotNull('send_date')
            ->where('send_date', '<=', now())
            ->limit(100)
            ->get();

        foreach ($due as $rec) {
            SendNoteEmail::dispatch($rec->note_id, $rec->id);
        }

        $this->info("Dispatched {$due->count()} emails");
        return self::SUCCESS;
    }
}


