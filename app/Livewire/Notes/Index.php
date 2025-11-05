<?php

namespace App\Livewire\Notes;

use App\Models\Note;
use App\Models\NoteRecipient;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Index extends Component
{
    #[Validate('required|string|min:3')]
    public string $title = '';

    #[Validate('required|string|min:3')]
    public string $body = '';

    #[Validate('nullable|date')]
    public ?string $send_date = null;

    #[Validate('required|string')]
    public string $recipients = '';

    public string $status = '';

    public function save(): void
    {
        $this->validate();

        $user = Auth::user();

        $note = Note::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'title' => $this->title,
            'body' => $this->body,
            'send_date' => $this->send_date,
            'heart_count' => 0,
            'is_published' => true,
        ]);

        $emails = collect(preg_split('/[\n,]+/', $this->recipients))
            ->map(fn ($e) => trim($e))
            ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique();

        foreach ($emails as $email) {
            NoteRecipient::firstOrCreate(
                ['note_id' => $note->id, 'email' => $email],
                ['token' => Str::uuid(), 'send_date' => $this->send_date]
            );
        }

        $this->reset(['title', 'body', 'send_date', 'recipients']);
        $this->status = 'Note created. Scheduled emails will send automatically.';
    }

    public function render()
    {
        $notes = Note::query()
            ->where('user_id', optional(Auth::user())->id)
            ->latest()
            ->limit(25)
            ->get();

        return view('livewire.notes.index', [
            'notes' => $notes,
        ]);
    }
}


