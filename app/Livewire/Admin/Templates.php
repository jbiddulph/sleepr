<?php

namespace App\Livewire\Admin;

use App\Models\Template;
use Illuminate\Support\Str;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Templates extends Component
{
    public string $status = '';

    #[Validate('required|string|min:2|max:80')]
    public string $name = '';

    #[Validate('nullable|string|min:2|max:80')]
    public ?string $slug = null;

    #[Validate('required|string|min:10')]
    public string $html = '<h1>{{title}}</h1><div>{{body}}</div><p><a href="{{heart_url}}">❤</a></p>';

    public bool $is_active = true;

    public ?string $edit_id = null;

    // Modal state
    public bool $showCreateModal = false;

    private function compilePreview(): string
    {
        $raw = (string) $this->html;
        if ($raw === '') {
            return '';
        }
        $subject = e('Sample Subject');
        $title = e('Sample Title');
        $body = nl2br(e("Sample body goes here.\nYou can use multiple lines."));
        $heart = '#';
        $email = e('recipient@example.com');
        return str_replace([
            '{{subject}}',
            '{{title}}',
            '{{body}}',
            '{{heart_url}}',
            '{{recipient_email}}',
        ], [
            $subject,
            $title,
            $body,
            $heart,
            $email,
        ], $raw);
    }

    public function save(): void
    {
        $this->validate();
        $slug = $this->slug ? Str::slug($this->slug) : Str::slug($this->name);

        if ($this->edit_id) {
            $tpl = Template::findOrFail($this->edit_id);
            $tpl->fill([
                'name' => $this->name,
                'slug' => $slug,
                'html' => $this->html,
                'is_active' => $this->is_active,
            ])->save();
            $this->status = __('Template updated.');
        } else {
            $tpl = Template::create([
                'id' => (string) Str::uuid(),
                'name' => $this->name,
                'slug' => $slug,
                'html' => $this->html,
                'is_active' => $this->is_active,
            ]);
            $this->status = __('Template created.');
        }

        $this->showCreateModal = false;
        $this->resetForm();
    }

    public function openCreateModal(): void
    {
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->resetForm();
    }

    public function edit(string $id): void
    {
        $tpl = Template::findOrFail($id);
        $this->edit_id = $tpl->id;
        $this->name = (string) $tpl->name;
        $this->slug = (string) $tpl->slug;
        $this->html = (string) $tpl->html;
        $this->is_active = (bool) $tpl->is_active;
        $this->showCreateModal = false; // Close create modal if editing
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function delete(string $id): void
    {
        Template::whereKey($id)->delete();
        $this->status = __('Template deleted.');
        if ($this->edit_id === $id) {
            $this->resetForm();
        }
    }

    private function resetForm(): void
    {
        $this->edit_id = null;
        $this->name = '';
        $this->slug = null;
        $this->html = '<h1>{{title}}</h1><div>{{body}}</div><p><a href="{{heart_url}}">❤</a></p>';
        $this->is_active = true;
    }

    public function render()
    {
        $templates = Template::query()->latest()->paginate(10);
        $preview = $this->compilePreview();
        return view('livewire.admin.templates', [
            'templates' => $templates,
            'preview' => $preview,
        ]);
    }
}


