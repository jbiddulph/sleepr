<?php

namespace Tests\Feature;

use App\Mail\NoteMail;
use App\Models\Note;
use App\Models\NoteRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class RescheduleStuckNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_reschedules_overdue_unsent_notes_and_leaves_others_alone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20 13:00:00', 'UTC'));

        $user = User::factory()->create();

        $stuck = $this->makeNote($user, 'Stuck outreach', 'stuck@example.com', '2026-08-04 08:10:00');
        $alreadySent = $this->makeNote($user, 'Already sent', 'sent@example.com', '2026-08-04 08:10:00', sent: true);
        $future = $this->makeNote($user, 'Future note', 'future@example.com', '2026-10-01 08:10:00');

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\SendNoteEmail']),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => time(),
            'created_at' => time(),
        ]);

        $this->artisan('notes:reschedule-stuck', [
            '--at' => '2026-09-21 08:10',
            '--timezone' => 'UTC',
        ])->assertSuccessful()
            ->expectsOutputToContain('Rescheduled 1 note(s) / 1 recipient(s) to 2026-09-21 08:10:00 (UTC).');

        $this->assertSame('2026-09-21 08:10:00', $stuck->fresh()->send_date->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-21 08:10:00', $stuck->recipients()->first()->send_date->format('Y-m-d H:i:s'));
        $this->assertNull($stuck->recipients()->first()->sent_at);

        $this->assertSame('2026-08-04 08:10:00', $alreadySent->fresh()->send_date->format('Y-m-d H:i:s'));
        $this->assertNotNull($alreadySent->recipients()->first()->sent_at);

        $this->assertSame('2026-10-01 08:10:00', $future->fresh()->send_date->format('Y-m-d H:i:s'));

        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_dry_run_does_not_change_send_dates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20 13:00:00', 'UTC'));

        $user = User::factory()->create();
        $stuck = $this->makeNote($user, 'Stuck outreach', 'stuck@example.com', '2026-08-04 08:10:00');

        $this->artisan('notes:reschedule-stuck', [
            '--at' => '2026-09-21 08:10',
            '--dry-run' => true,
        ])->assertSuccessful()
            ->expectsOutputToContain('Would reschedule 1 note(s) / 1 recipient(s)');

        $this->assertSame('2026-08-04 08:10:00', $stuck->fresh()->send_date->format('Y-m-d H:i:s'));
    }

    public function test_send_due_sends_rescheduled_notes_at_the_new_time(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-09-20 13:00:00', 'UTC'));

        $user = User::factory()->create();
        $stuck = $this->makeNote($user, 'Stuck outreach', 'stuck@example.com', '2026-08-04 08:10:00');

        $this->artisan('notes:reschedule-stuck')->assertSuccessful();

        $this->artisan('notes:send-due')->assertSuccessful();
        Mail::assertNothingSent();
        $this->assertNull($stuck->recipients()->first()->sent_at);

        Carbon::setTestNow(Carbon::parse('2026-09-21 08:10:00', 'UTC'));

        $this->artisan('notes:send-due')->assertSuccessful();

        Mail::assertSent(NoteMail::class, function (NoteMail $mail): bool {
            return $mail->hasTo('stuck@example.com');
        });
        $this->assertNotNull($stuck->recipients()->first()->fresh()->sent_at);
    }

    private function makeNote(
        User $user,
        string $title,
        string $email,
        string $sendDate,
        bool $sent = false,
    ): Note {
        $note = Note::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'title' => $title,
            'subject' => 'simple way to help your clients with trusted local tradespeople',
            'body' => 'Please find this outreach note.',
            'recipients' => $email,
            'send_date' => $sendDate,
            'heart_count' => 0,
            'is_published' => true,
        ]);

        NoteRecipient::create([
            'note_id' => $note->id,
            'email' => $email,
            'token' => (string) Str::uuid(),
            'send_date' => $sendDate,
            'sent_at' => $sent ? $sendDate : null,
        ]);

        return $note;
    }
}
