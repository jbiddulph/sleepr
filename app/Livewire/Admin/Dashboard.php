<?php

namespace App\Livewire\Admin;

use App\Models\Note;
use App\Models\NoteRecipient;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $totals = [
            'notes' => Note::count(),
            'recipients' => NoteRecipient::count(),
            'sent' => NoteRecipient::whereNotNull('sent_at')->count(),
            'hearted' => NoteRecipient::whereNotNull('hearted_at')->count(),
        ];

        $hearted = DB::table('note_recipients as r')
            ->join('notes as n', 'n.id', '=', 'r.note_id')
            ->select('r.email', 'r.hearted_at', 'n.title as note_title', 'n.id as note_id')
            ->whereNotNull('r.hearted_at')
            ->orderByDesc('r.hearted_at')
            ->limit(100)
            ->get();

        return view('livewire.admin.dashboard', [
            'totals' => $totals,
            'hearted' => $hearted,
        ]);
    }
}


