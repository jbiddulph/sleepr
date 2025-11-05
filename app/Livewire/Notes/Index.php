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
    public string $body = '';

    #[Validate('nullable|date')]
    public ?string $send_date = null;

    #[Validate('required|string')]
    public string $recipients = '';

    public string $status = '';

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
    public string $edit_body = '';
    public ?string $edit_send_date = null; // datetime-local string or null

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

        $this->reset(['title', 'body', 'send_date', 'recipients', 'template_id', 'attachments']);
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

    public function updatedTemplateId(): void
    {
        $this->refreshPreview();
    }

    public function updatedTitle(): void
    {
        $this->refreshPreview();
    }

    public function updatedBody(): void
    {
        $this->refreshPreview();
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
            $safeTitle = e((string) $this->title ?: 'Email title');
            $safeBody = nl2br(e((string) $this->body ?: 'Your email body will appear here…'));
            $this->preview_html = str_replace([
                '{{title}}',
                '{{body}}',
                '{{heart_url}}',
                '{{recipient_email}}',
            ], [
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


