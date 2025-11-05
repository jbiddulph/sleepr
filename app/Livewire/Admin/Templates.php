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
            if ($this->is_active) {
                Template::where('id', '!=', $tpl->id)->update(['is_active' => false]);
            }
            $this->status = __('Template updated.');
        } else {
            $tpl = Template::create([
                'id' => (string) Str::uuid(),
                'name' => $this->name,
                'slug' => $slug,
                'html' => $this->html,
                'is_active' => $this->is_active,
            ]);
            if ($this->is_active) {
                Template::where('id', '!=', $tpl->id)->update(['is_active' => false]);
            }
            $this->status = __('Template created.');
        }

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
        return view('livewire.admin.templates', [
            'templates' => $templates,
        ]);
    }
}


