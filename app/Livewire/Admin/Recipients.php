<?php

namespace App\Livewire\Admin;

use App\Models\NoteRecipient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Recipients extends Component
{
    public string $query = '';
    public ?int $editId = null;
    public string $editEmail = '';
    public ?string $editSendDate = null;

    public function edit(int $id): void
    {
        $r = NoteRecipient::findOrFail($id);
        $this->editId = $r->id;
        $this->editEmail = (string) $r->email;
        $this->editSendDate = $r->send_date ? Carbon::parse($r->send_date)->format('Y-m-d\TH:i') : null;
    }

    public function save(): void
    {
        if (!$this->editId) return;
        $this->validate([
            'editEmail' => 'required|email',
            'editSendDate' => 'nullable|date',
        ]);

        $r = NoteRecipient::findOrFail($this->editId);
        $r->email = $this->editEmail;
        $r->send_date = $this->editSendDate ? Carbon::parse($this->editSendDate) : null;
        $r->save();

        $this->reset(['editId','editEmail','editSendDate']);
        $this->dispatch('saved');
    }

    public function cancel(int $id): void
    {
        $r = NoteRecipient::findOrFail($id);
        if (is_null($r->sent_at)) {
            $r->delete();
            $this->dispatch('deleted');
        }
    }

    public function getRowsProperty()
    {
        $q = NoteRecipient::query()->with('note')->latest();
        if ($this->query !== '') {
            $q->whereRaw('email ILIKE ?', ['%'.$this->query.'%']);
        }
        return $q->limit(50)->get();
    }

    public function render()
    {
        return view('livewire.admin.recipients', [
            'rows' => $this->rows,
        ]);
    }
}


