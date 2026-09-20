<?php

namespace App\Console\Commands;

use App\Jobs\SendNoteEmail;
use App\Models\NoteRecipient;
use Illuminate\Console\Command;

class SendDueNotes extends Command
{
    protected $signature = 'notes:send-due';

    protected $description = 'Send due note emails immediately';

    public function handle(): int
    {
        $due = NoteRecipient::query()
            ->whereNull('sent_at')
            ->whereNotNull('send_date')
            ->where('send_date', '<=', now())
            ->orderBy('send_date')
            ->limit(100)
            ->get();

        $sent = 0;
        $failed = 0;

        foreach ($due as $rec) {
            try {
                // Send in-process so Heroku does not depend on a separate queue worker.
                SendNoteEmail::dispatchSync($rec->note_id, $rec->id);
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                report($e);
                $this->error("Failed to send note {$rec->note_id} to {$rec->email}: {$e->getMessage()}");
            }
        }

        $this->info("Sent {$sent} of {$due->count()} due emails".($failed ? " ({$failed} failed)" : ''));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
