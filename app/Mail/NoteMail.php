<?php

namespace App\Mail;

use App\Models\Note;
use App\Models\NoteRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NoteMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Note $note, public NoteRecipient $recipient)
    {
    }

    public function build()
    {
        $heartUrl = route('notes.heart', ['token' => $this->recipient->token]);

        return $this->subject('A note for you')
            ->view('emails.note')
            ->with([
                'note' => $this->note,
                'recipient' => $this->recipient,
                'heartUrl' => $this->buildViewData()['heartUrl'] ?? $heartUrl,
            ]);
    }
}


