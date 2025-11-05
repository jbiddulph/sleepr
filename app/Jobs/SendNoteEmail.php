<?php

namespace App\Jobs;
use App\Mail\NoteMail;
use App\Models\Note;
use App\Models\NoteRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendNoteEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $noteId, public int $recipientId)
    {
    }

    public function handle(): void
    {
        $recipient = NoteRecipient::findOrFail($this->recipientId);
        $note = Note::findOrFail($this->noteId);

        Mail::to($recipient->email)->send(new NoteMail($note, $recipient));

        $recipient->forceFill(['sent_at' => now()])->save();
    }
}


