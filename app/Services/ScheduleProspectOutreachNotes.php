<?php

namespace App\Services;

use App\Models\EstateAgentOutreachTemplate;
use App\Models\EstateAgentProspect;
use App\Models\EstateAgentProspectNote;
use App\Models\Note;
use App\Models\NoteRecipient;
use App\Models\Template;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ScheduleProspectOutreachNotes
{
    private const BATCH_SIZE = 33;

    public function schedule(
        Collection $prospects,
        EstateAgentOutreachTemplate $template,
        User $user,
        Carbon $startAt,
        bool $stagger = true,
    ): int {
        $mailTemplate = $this->skeletonTemplate();
        $created = 0;

        DB::transaction(function () use ($prospects, $template, $user, $startAt, $stagger, $mailTemplate, &$created): void {
            foreach ($prospects->values() as $index => $prospect) {
                $emailTo = $this->resolveRecipientEmail($prospect);

                if (! $emailTo) {
                    continue;
                }

                $subject = $template->renderSubject($prospect, $user);
                $body = $template->renderBody($prospect, $user);
                $sendAt = $this->sendTimeForIndex($startAt, $index, $stagger);

                if ($this->alreadyScheduled($prospect, $subject, $emailTo)) {
                    continue;
                }

                $note = Note::create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->getKey(),
                    'title' => trim($prospect->agency_name.' — '.$prospect->town),
                    'subject' => $subject,
                    'body' => $body,
                    'recipients' => $emailTo,
                    'send_date' => $sendAt,
                    'heart_count' => 0,
                    'is_published' => true,
                    'template_id' => $mailTemplate->id,
                ]);

                NoteRecipient::create([
                    'note_id' => $note->id,
                    'email' => $emailTo,
                    'token' => (string) Str::uuid(),
                    'send_date' => $sendAt,
                ]);

                EstateAgentProspectNote::create([
                    'id' => (string) Str::uuid(),
                    'prospect_id' => $prospect->id,
                    'created_by' => $user->getKey(),
                    'note_type' => 'email_draft',
                    'subject' => $subject,
                    'body' => $body,
                    'email_to' => $emailTo,
                    'email_from' => $user->email,
                    'note_id' => $note->id,
                    'scheduled_send_at' => $sendAt,
                ]);

                if (! $prospect->selected_email) {
                    $prospect->update(['selected_email' => $emailTo]);
                }

                $created++;
            }
        });

        return $created;
    }

    public function validateSendDate(?string $sendDate): ?string
    {
        if (! $sendDate) {
            return __('Choose a send date and time.');
        }

        $parsed = Carbon::parse($sendDate, 'UTC');

        if ($parsed->isPast()) {
            return __('Send time must be in the future.');
        }

        if ($parsed->minute % 10 !== 0) {
            return __('Send time must be in 10-minute increments (00, 10, 20, 30, 40, 50).');
        }

        return null;
    }

    private function resolveRecipientEmail(EstateAgentProspect $prospect): ?string
    {
        return $prospect->selected_email
            ?: ($prospect->best_emails[0] ?? null)
            ?: ($prospect->emails_found[0] ?? null);
    }

    private function sendTimeForIndex(Carbon $startAt, int $index, bool $stagger): Carbon
    {
        if (! $stagger) {
            return $startAt->copy();
        }

        $slot = intdiv($index, self::BATCH_SIZE);

        return $startAt->copy()->addMinutes($slot * 10);
    }

    private function alreadyScheduled(EstateAgentProspect $prospect, string $subject, string $emailTo): bool
    {
        return EstateAgentProspectNote::query()
            ->where('prospect_id', $prospect->id)
            ->where('note_type', 'email_draft')
            ->where('subject', $subject)
            ->where('email_to', $emailTo)
            ->whereNotNull('note_id')
            ->whereNotNull('scheduled_send_at')
            ->where('scheduled_send_at', '>', now())
            ->exists();
    }

    private function skeletonTemplate(): Template
    {
        return Template::firstOrCreate(
            ['slug' => 'skeleton'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Skeleton',
                'html' => '<!doctype html><html><body>{{body}}</body></html>',
                'is_active' => true,
            ]
        );
    }
}
