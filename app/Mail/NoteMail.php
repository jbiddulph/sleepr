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
            $slug = (string) ($this->note->template->slug ?? '');
            // 1) If a Blade view exists for the template slug, use it (e.g. resources/views/emails/{slug}.blade.php)
            if ($slug && \Illuminate\Support\Facades\View::exists('emails.'.$slug)) {
                $mailable = $this->subject('A note for you')
                    ->view('emails.'.$slug, [
                        'note' => $this->note,
                        'recipient' => $this->recipient,
                        'heartUrl' => $heartUrl,
                    ]);
            } else {
                // 2) Otherwise, render stored HTML from DB with placeholders
                $rawHtml = (string) ($this->note->template->html ?? '');
                if ($rawHtml !== '') {
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
                    // 3) Fallback to default view
                    $mailable = $this->subject('A note for you')
                        ->view($view, [
                            'note' => $this->note,
                            'recipient' => $this->recipient,
                            'heartUrl' => $heartUrl,
                        ]);
                }
            }
        } else {
            $mailable = $this->subject('A note for you')
                ->view($view, [
                    'note' => $this->note,
                    'recipient' => $this->recipient,
                    'heartUrl' => $heartUrl,
                ]);
        }

        // Attach files from note attachments (public URLs)
        foreach ($this->note->attachments as $att) {
            try {
                $tmp = tempnam(sys_get_temp_dir(), 'att_');
                $data = @file_get_contents($att->url);
                if ($data !== false) {
                    file_put_contents($tmp, $data);
                    $name = $att->name ?: basename(parse_url($att->url, PHP_URL_PATH));
                    $mailable->attach($tmp, ['as' => $name]);
                }
            } catch (\Throwable $e) {
                // skip attachment errors
            }
        }

        return $mailable;
    }
}


