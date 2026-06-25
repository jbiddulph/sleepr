<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\NoteAttachment;
use App\Models\NoteRecipient;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CreateZapTaskOutreachNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_the_zaptask_outreach_batch(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-25 21:33:00', 'UTC'));

        $user = User::factory()->create();
        $recipients = collect(range(1, 100))
            ->map(fn (int $number): string => sprintf('agent%03d@example.com', $number))
            ->all();

        $this->artisan('notes:create-zaptask-outreach', [
            '--user' => $user->email,
            '--recipient' => $recipients,
        ])->assertSuccessful();

        $this->assertDatabaseHas('templates', [
            'slug' => 'skeleton',
            'name' => 'Skeleton',
        ]);

        $template = Template::where('slug', 'skeleton')->firstOrFail();

        $this->assertSame(100, Note::count());
        $this->assertSame(100, NoteRecipient::count());
        $this->assertSame(0, NoteAttachment::count());

        $this->assertSame(33, NoteRecipient::where('send_date', '2026-06-26 10:00:00')->count());
        $this->assertSame(33, NoteRecipient::where('send_date', '2026-06-26 10:10:00')->count());
        $this->assertSame(34, NoteRecipient::where('send_date', '2026-06-26 10:20:00')->count());

        Note::query()->each(function (Note $note) use ($template): void {
            $this->assertSame('Hi', $note->title);
            $this->assertSame('simple way to help your clients with trusted local tradespeople', $note->subject);
            $this->assertStringContainsString('I wanted to introduce you to ZapTask (www.zaptask.co.uk)', $note->body);
            $this->assertStringContainsString("John Biddulph\nFounder, ZapTask\nwww.zaptask.co.uk", $note->body);
            $this->assertSame($template->id, $note->template_id);
            $this->assertTrue((bool) $note->is_published);
        });
    }

    public function test_command_requires_exactly_100_recipients(): void
    {
        User::factory()->create();

        $this->artisan('notes:create-zaptask-outreach', [
            '--recipient' => ['agent001@example.com'],
        ])->assertFailed();

        $this->assertSame(0, Note::count());
        $this->assertSame(0, NoteRecipient::count());
    }
}
