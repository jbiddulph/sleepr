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
        if ($this->note->template && method_exists($this->note->template, 'getAttribute')) {
            // Render dynamic HTML from stored template with simple placeholders
            $rawHtml = (string) $this->note->template->html;
            $safeTitle = e((string) $this->note->title);
            $safeBody = nl2br(e((string) $this->note->body));
            $html = str_replace([
                '{{title}}',
                '{{body}}',
                '{{heart_url}}',
                '{{recipient_email}}',
            ], [
                $safeTitle,
                $safeBody,
                $heartUrl,
                e($this->recipient->email),
            ], $rawHtml);

            $mailable = $this->subject('A note for you')->html($html);
        } else {
            $mailable = $this->subject('A note for you')
                ->view($view)
                ->with([
                    'note' => $this->note,
                    'recipient' => $this->recipient,
                    'heartUrl' => $heartUrl,
                ]);
        }

        return $mailable;
    }
}


