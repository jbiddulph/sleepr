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

    // Editing state
    public ?string $edit_note_id = null; // uuid
    public string $edit_title = '';
    public string $edit_body = '';
    public ?string $edit_send_date = null; // datetime-local string or null

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

    public function startEdit(string $noteId): void
    {
        $note = Note::whereKey($noteId)->where('user_id', Auth::id())->firstOrFail();
        $this->edit_note_id = $note->getKey();
        $this->edit_title = (string) $note->title;
        $this->edit_body = (string) $note->body;
        // send_date may be date or datetime; keep as string for input
        $this->edit_send_date = $note->send_date ? (string) $note->send_date : null;
    }

    public function cancelEdit(): void
    {
        $this->resetEditState();
    }

    public function updateNote(): void
    {
        if (!$this->edit_note_id) {
            return;
        }

        $this->validate([
            'edit_title' => ['required','string','min:3'],
            'edit_body' => ['required','string','min:3'],
            'edit_send_date' => ['nullable','date'],
        ]);

        $note = Note::whereKey($this->edit_note_id)->where('user_id', Auth::id())->firstOrFail();
        $note->title = $this->edit_title;
        $note->body = $this->edit_body;
        $note->send_date = $this->edit_send_date; // stored as-is; recipients use their own send_date
        $note->save();

        $this->status = __('Note updated.');
        $this->resetEditState();
    }

    public function deleteNote(string $noteId): void
    {
        $note = Note::whereKey($noteId)->where('user_id', Auth::id())->firstOrFail();
        $note->delete(); // cascades remove recipients
        $this->status = __('Note deleted.');
        if ($this->edit_note_id === $noteId) {
            $this->resetEditState();
        }
    }

    private function resetEditState(): void
    {
        $this->edit_note_id = null;
        $this->edit_title = '';
        $this->edit_body = '';
        $this->edit_send_date = null;
    }
}


