<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\NoteRecipient;
use Illuminate\Http\Request;

class NoteHeartController
{
    public function __invoke(string $token)
    {
        $recipient = NoteRecipient::where('token', $token)->firstOrFail();
        $note = $recipient->note;

        if (is_null($recipient->hearted_at)) {
            $recipient->forceFill(['hearted_at' => now()])->save();
            if ($note) {
                $note->increment('heart_count');
            }
        }

        return response()->view('notes.thankyou', [
            'note' => $note,
            'recipient' => $recipient,
        ]);
    }
}


