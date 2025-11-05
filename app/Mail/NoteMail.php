<?php

namespace App\Mail;

use App\Models\Note;
use App\Models\NoteRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\View;

class NoteMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Note $note, public NoteRecipient $recipient)
    {
    }

    public function build()
    {
        $heartUrl = route('notes.heart', ['token' => $this->recipient->token]);

        $view = 'emails.note';
        if ($this->note->relationLoaded('template') || $this->note->template) {
            $view = 'emails.note';
            if ($this->note->template && method_exists($this->note->template, 'getAttribute')) {
                // Render dynamic HTML from stored template
                $html = $this->note->template->html;
                return $this->subject('A note for you')
                    ->html($html)
                    ->with([
                        'note' => $this->note,
                        'recipient' => $this->recipient,
                        'heartUrl' => $heartUrl,
                    ]);
            }
        }

        return $this->subject('A note for you')
            ->view($view)
            ->with([
                'note' => $this->note,
                'recipient' => $this->recipient,
                'heartUrl' => $heartUrl,
            ]);
    }
}


