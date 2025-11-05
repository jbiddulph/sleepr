<?php

namespace App\Livewire\Notes;

use App\Models\Note;
use App\Models\NoteRecipient;
use App\Models\NoteAttachment;
use App\Models\Template;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Index extends Component
{
    #[Validate('required|string|min:3')]
    public string $title = '';

    #[Validate('required|string|min:3')]
    public string $subject = '';

    #[Validate('required|string|min:3')]
    public string $body = '';

    #[Validate('nullable|date')]
    public ?string $send_date = null;

    #[Validate('required|string')]
    public string $recipients = '';

    public string $status = '';

    // Modal state
    public bool $showCreateModal = false;
    public bool $showEditModal = false;

    // Template selection
    #[Validate('nullable|uuid|exists:templates,id')]
    public ?string $template_id = null;
    public array $templates = [];

    // Attachments selection (from Supabase bucket)
    public array $attachments = []; // [{name,url,size}]
    public array $bucketFiles = [];
    public string $bucketStatus = '';

    // Live template preview
    public ?string $preview_html = null;

    // Editing state
    public ?string $edit_note_id = null; // uuid
    public string $edit_title = '';
    public string $edit_subject = '';
    public string $edit_body = '';
    public ?string $edit_send_date = null; // datetime-local string or null
    public string $edit_recipients = ''; // comma or newline separated emails

    public function mount(): void
    {
        $this->templates = Template::query()->orderBy('name')->get(['id','name'])->toArray();
        $this->refreshPreview();
    }

    public function save(): void
    {
        $this->validate();

        // Enforce 10-minute increments on server too
        if ($this->send_date) {
            $dt = Carbon::parse($this->send_date, 'UTC');
            if ($dt->minute % 10 !== 0) {
                $this->addError('send_date', __('Send time must be in 10-minute increments (00,10,20,30,40,50).'));
                return;
            }
        }

        $user = Auth::user();

        $note = Note::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'title' => $this->title,
            'subject' => $this->subject,
            'body' => $this->body,
            'send_date' => $this->send_date,
            'heart_count' => 0,
            'is_published' => true,
            'template_id' => $this->template_id,
        ]);

        $emails = collect(preg_split('/[\n,]+/', $this->recipients))
            ->map(fn ($e) => trim($e))
            ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique();

        // Save recipients string to note for reporting
        $note->recipients = $emails->join(', ');
        $note->save();

        foreach ($emails as $email) {
            NoteRecipient::firstOrCreate(
                ['note_id' => $note->id, 'email' => $email],
                ['token' => Str::uuid(), 'send_date' => $this->send_date]
            );
        }

        // Persist selected attachments
        foreach ($this->attachments as $att) {
            if (!isset($att['url'])) { continue; }
            NoteAttachment::create([
                'id' => (string) Str::uuid(),
                'note_id' => $note->id,
                'name' => $att['name'] ?? basename(parse_url($att['url'], PHP_URL_PATH) ?? ''),
                'url' => $att['url'],
                'size' => isset($att['size']) ? (int) $att['size'] : null,
            ]);
        }

        $this->reset(['title', 'subject', 'body', 'send_date', 'recipients', 'template_id', 'attachments']);
        $this->showCreateModal = false;
        $this->status = 'Note created. Scheduled emails will send automatically.';
    }

    public function openCreateModal(): void
    {
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->reset(['title', 'subject', 'body', 'send_date', 'recipients', 'template_id', 'attachments']);
    }

    public function render()
    {
        $notes = Note::query()
            ->where('user_id', optional(Auth::user())->id)
            ->with(['recipients' => function ($q) {
                $q->select('id', 'note_id', 'email')->orderBy('email');
            }])
            ->withCount([
                'recipients as total_recipients',
                'recipients as sent_recipients_count' => function ($q) {
                    $q->whereNotNull('sent_at');
                },
            ])
            ->addSelect([
                'last_sent_at' => NoteRecipient::selectRaw('MAX(sent_at)')
                    ->whereColumn('note_id', 'notes.id'),
            ])
            ->latest()
            ->limit(25)
            ->get();

        return view('livewire.notes.index', [
            'notes' => $notes,
        ]);
    }

    public function startEdit(string $noteId): void
    {
        $note = Note::with(['attachments', 'recipients'])->whereKey($noteId)->where('user_id', Auth::id())->firstOrFail();
        $this->edit_note_id = $note->getKey();
        $this->edit_title = (string) $note->title;
        $this->edit_subject = (string) ($note->subject ?? '');
        $this->edit_body = (string) $note->body;
        // send_date may be date or datetime; keep as string for input
        $this->edit_send_date = $note->send_date ? (string) $note->send_date : null;
        $this->template_id = $note->template_id;
        $this->attachments = $note->attachments->map(fn ($a) => [
            'name' => $a->name,
            'url' => $a->url,
            'size' => $a->size,
        ])->values()->all();
        // Load existing recipients - use recipients field if available, otherwise fall back to relationship
        $recipientsString = $note->getAttribute('recipients'); // Get the column value
        if (!empty($recipientsString)) {
            $this->edit_recipients = $recipientsString;
        } else {
            $this->edit_recipients = $note->getRelationValue('recipients')?->pluck('email')->join(', ') ?? '';
        }
        $this->showEditModal = true;
        $this->refreshPreview();
    }

    public function cancelEdit(): void
    {
        $this->resetEditState();
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->resetEditState();
    }

    public function updateNote(): void
    {
        if (!$this->edit_note_id) {
            return;
        }

        $this->validate([
            'edit_title' => ['required','string','min:3'],
            'edit_subject' => ['required','string','min:3'],
            'edit_body' => ['required','string','min:3'],
            'edit_send_date' => ['nullable','date'],
            'edit_recipients' => ['required','string'],
        ]);

        // Enforce 10-minute increments on server too
        if ($this->edit_send_date) {
            $dt = Carbon::parse($this->edit_send_date, 'UTC');
            if ($dt->minute % 10 !== 0) {
                $this->addError('edit_send_date', __('Send time must be in 10-minute increments (00,10,20,30,40,50).'));
                return;
            }
        }

        $note = Note::with(['attachments', 'recipients'])->whereKey($this->edit_note_id)->where('user_id', Auth::id())->firstOrFail();
        $note->title = $this->edit_title;
        $note->subject = $this->edit_subject;
        $note->body = $this->edit_body;
        $note->send_date = $this->edit_send_date; // stored as-is; recipients use their own send_date
        $note->template_id = $this->template_id;
        $note->save();

        // Update recipients: parse emails, add new ones, remove ones no longer in the list
        $emails = collect(preg_split('/[\n,]+/', $this->edit_recipients))
            ->map(fn ($e) => trim($e))
            ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique();

        // Save recipients string to note for reporting
        $note->recipients = $emails->join(', ');
        $note->save();

        // Get existing recipient emails
        $existingEmails = $note->recipients()->pluck('email')->toArray();

        // Add new recipients
        foreach ($emails as $email) {
            if (!in_array($email, $existingEmails, true)) {
                NoteRecipient::create([
                    'note_id' => $note->id,
                    'email' => $email,
                    'token' => Str::uuid(),
                    'send_date' => $this->edit_send_date,
                ]);
            }
        }

        // Remove recipients that are no longer in the list (only if not sent yet)
        foreach ($note->recipients()->get() as $recipient) {
            if (!in_array($recipient->email, $emails->toArray(), true) && !$recipient->sent_at) {
                $recipient->delete();
            }
        }

        // Replace attachments with current selection
        $note->attachments()->delete();
        foreach ($this->attachments as $att) {
            if (!isset($att['url'])) { continue; }
            NoteAttachment::create([
                'id' => (string) Str::uuid(),
                'note_id' => $note->id,
                'name' => $att['name'] ?? basename(parse_url($att['url'], PHP_URL_PATH) ?? ''),
                'url' => $att['url'],
                'size' => isset($att['size']) ? (int) $att['size'] : null,
            ]);
        }

        $this->status = __('Note updated.');
        $this->showEditModal = false;
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

    public function copyNote(string $noteId): void
    {
        $user = Auth::user();
        $originalNote = Note::with(['recipients', 'attachments'])
            ->whereKey($noteId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Get recipient emails from original note - use relationship collection
        $recipientsCollection = $originalNote->getRelationValue('recipients');
        $recipientEmails = $recipientsCollection ? $recipientsCollection->pluck('email')->toArray() : [];
        
        // Use recipients column if available, otherwise build from relationship
        $recipientsString = $originalNote->getAttribute('recipients') ?: implode(', ', $recipientEmails);

        // Create new note with [COPY] prefix
        $newNote = Note::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'title' => '[COPY] ' . $originalNote->title,
            'subject' => $originalNote->subject,
            'body' => $originalNote->body,
            'recipients' => $recipientsString,
            'send_date' => $originalNote->send_date,
            'heart_count' => 0,
            'is_published' => true,
            'template_id' => $originalNote->template_id,
        ]);

        // Copy recipients
        if ($recipientsCollection) {
            foreach ($recipientsCollection as $recipient) {
                NoteRecipient::create([
                    'note_id' => $newNote->id,
                    'email' => $recipient->email,
                    'token' => Str::uuid(),
                    'send_date' => $recipient->send_date ?? $originalNote->send_date,
                ]);
            }
        }

        // Copy attachments
        foreach ($originalNote->attachments as $attachment) {
            NoteAttachment::create([
                'id' => (string) Str::uuid(),
                'note_id' => $newNote->id,
                'name' => $attachment->name,
                'url' => $attachment->url,
                'size' => $attachment->size,
            ]);
        }

        $this->status = __('Note copied successfully.');
    }

    private function resetEditState(): void
    {
        $this->edit_note_id = null;
        $this->edit_title = '';
        $this->edit_subject = '';
        $this->edit_body = '';
        $this->edit_send_date = null;
        $this->edit_recipients = '';
        $this->template_id = null;
        $this->attachments = [];
        $this->showEditModal = false;
    }

    // Manual reload button
    public function reloadPreview(): void
    {
        $this->refreshPreview();
    }

    // Supabase helpers (list bucket files and manage selection)
    public function fetchBucketFiles(): void
    {
        $bucket = config('filesystems.disks.supabase.bucket') ?? env('SUPABASE_BUCKET');
        $url = rtrim(env('SUPABASE_URL', ''), '/');
        $key = env('SUPABASE_SERVICE_KEY', env('SUPABASE_ANON_KEY'));
        if (!$bucket || !$url || !$key) {
            $this->bucketStatus = __('Missing SUPABASE configuration.');
            return;
        }
        try {
            $endpoint = $url.'/storage/v1/object/list/'.rawurlencode($bucket);
            $resp = Http::withHeaders([
                'apikey' => $key,
                'Authorization' => 'Bearer '.$key,
                'Content-Type' => 'application/json',
            ])->post($endpoint, [
                'prefix' => '',
                'limit' => 100,
                'offset' => 0,
                'sortBy' => [ 'column' => 'name', 'order' => 'asc' ],
            ]);
            if ($resp->failed()) {
                $this->bucketStatus = __('Failed to load files.');
                return;
            }
            $files = $resp->json() ?: [];
            $this->bucketFiles = collect($files)
                ->filter(fn ($f) => isset($f['name']) && empty($f['metadata']['is_directory'] ?? false))
                ->map(function ($f) use ($url, $bucket) {
                    $public = rtrim($url, '/').'/storage/v1/object/public/'.rawurlencode($bucket).'/'.$f['name'];
                    return [
                        'name' => $f['name'],
                        'url' => $public,
                        'size' => $f['metadata']['size'] ?? null,
                    ];
                })->values()->all();
            $this->bucketStatus = __('Loaded :n files', ['n' => count($this->bucketFiles)]);
        } catch (\Throwable $e) {
            $this->bucketStatus = __('Error: ').$e->getMessage();
        }
    }

    public function addAttachment(string $url, string $name = '', $size = null): void
    {
        $exists = collect($this->attachments)->firstWhere('url', $url);
        if (!$exists) {
            $this->attachments[] = ['url' => $url, 'name' => $name, 'size' => $size];
        }
    }

    public function removeAttachment(string $url): void
    {
        $this->attachments = collect($this->attachments)->reject(fn ($a) => $a['url'] === $url)->values()->all();
    }

    public function updated($name, $value): void
    {
        if (in_array($name, ['template_id', 'title', 'subject', 'body', 'edit_title', 'edit_subject', 'edit_body'], true)) {
            $this->refreshPreview();
        }
    }

    private function refreshPreview(): void
    {
        try {
            $raw = null;
            if ($this->template_id) {
                $tpl = Template::find($this->template_id);
                $raw = $tpl?->html;
            }
            if (!$raw) {
                // Fallback simple preview
                $this->preview_html = null;
                return;
            }
            // Use edit fields if in edit mode, otherwise use create fields
            $isEditMode = !empty($this->edit_note_id);
            $safeSubject = e((string) ($isEditMode ? ($this->edit_subject ?: 'Sample Subject') : ($this->subject ?: 'Sample Subject')));
            $safeTitle = e((string) ($isEditMode ? ($this->edit_title ?: 'Sample Title') : ($this->title ?: 'Sample Title')));
            $safeBody = nl2br(e((string) ($isEditMode ? ($this->edit_body ?: 'Your email body will appear here…') : ($this->body ?: 'Your email body will appear here…'))));
            $this->preview_html = str_replace([
                '{{subject}}',
                '{{title}}',
                '{{body}}',
                '{{heart_url}}',
                '{{recipient_email}}',
            ], [
                $safeSubject,
                $safeTitle,
                $safeBody,
                '#',
                'recipient@example.com',
            ], (string) $raw);
        } catch (\Throwable $e) {
            $this->preview_html = null;
        }
    }
}


