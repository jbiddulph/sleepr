<?php

namespace App\Services;

use App\Models\Note;
use App\Models\NoteRecipient;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RescheduleStuckNotes
{
    /**
     * @return array{
     *     notes: int,
     *     recipients: int,
     *     jobs_cleared: int,
     *     rows: Collection<int, array{title: string, email: string, previous_send_at: ?string}>
     * }
     */
    public function handle(CarbonInterface $sendAt, bool $dryRun = false): array
    {
        $now = now();

        $stuckNotes = Note::query()
            ->whereNotNull('send_date')
            ->where('send_date', '<=', $now)
            ->whereHas('recipients', fn ($query) => $query->whereNull('sent_at'))
            ->with(['recipients' => fn ($query) => $query->whereNull('sent_at')->orderBy('email')])
            ->orderBy('send_date')
            ->get();

        $rows = $stuckNotes->flatMap(function (Note $note) {
            return $note->getRelationValue('recipients')->map(function (NoteRecipient $recipient) use ($note) {
                $previous = $recipient->send_date ?? $note->send_date;

                return [
                    'title' => (string) $note->title,
                    'email' => (string) $recipient->email,
                    'previous_send_at' => $previous?->toDateTimeString(),
                ];
            });
        })->values();

        $result = [
            'notes' => $stuckNotes->count(),
            'recipients' => $rows->count(),
            'jobs_cleared' => 0,
            'rows' => $rows,
        ];

        if ($dryRun || $stuckNotes->isEmpty()) {
            return $result;
        }

        $noteIds = $stuckNotes->pluck('id');
        $recipientIds = $stuckNotes
            ->flatMap(fn (Note $note) => $note->getRelationValue('recipients')->pluck('id'))
            ->unique()
            ->values();

        DB::transaction(function () use ($noteIds, $recipientIds, $sendAt, &$result): void {
            Note::query()->whereIn('id', $noteIds)->update(['send_date' => $sendAt]);
            NoteRecipient::query()->whereIn('id', $recipientIds)->update(['send_date' => $sendAt]);
            $result['jobs_cleared'] = $this->clearPendingSendJobs();
        });

        return $result;
    }

    private function clearPendingSendJobs(): int
    {
        if (! Schema::hasTable('jobs')) {
            return 0;
        }

        return DB::table('jobs')
            ->where(function ($query): void {
                $query->where('payload', 'like', '%SendNoteEmail%')
                    ->orWhere('payload', 'like', '%NoteMail%');
            })
            ->delete();
    }
}
