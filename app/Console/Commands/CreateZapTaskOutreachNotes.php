<?php

namespace App\Console\Commands;

use App\Models\Note;
use App\Models\NoteRecipient;
use App\Models\Template;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateZapTaskOutreachNotes extends Command
{
    protected $signature = 'notes:create-zaptask-outreach
        {--user= : Owner user email address or ID}
        {--recipient=* : Recipient email address; repeat or pass comma/newline separated values}
        {--recipients-file= : File containing recipient email addresses}
        {--date= : Send date in Y-m-d format; defaults to tomorrow}
        {--timezone= : Timezone for interpreting 10:00, 10:10, and 10:20; defaults to app timezone}
        {--dry-run : Show what would be created without writing to the database}
        {--allow-duplicates : Allow creation even when matching notes already exist}';

    protected $description = 'Create the 100-note ZapTask outreach batch in 33/33/34 scheduled groups.';

    private const TITLE = 'Hi';

    private const SUBJECT = 'simple way to help your clients with trusted local tradespeople';

    private const BODY = <<<'TEXT'
I hope you're well.

I wanted to introduce you to ZapTask (www.zaptask.co.uk), a new platform that connects homeowners, landlords and tenants with trusted local tradespeople and service providers.

As an estate agent, your clients regularly ask for recommendations for cleaners, plumbers, electricians, gardeners, removals, decorators and other local services. ZapTask provides a simple place where they can quickly find reliable professionals, saving your team time responding to these requests.

Whether it's preparing a property for sale, arranging repairs, or helping new homeowners settle in, ZapTask makes it easy to connect people with local businesses they can trust.

I'd love to show you how ZapTask could benefit both your agency and your clients. If you're interested, I'd be happy to arrange a quick 10-minute chat or answer any questions by email.

You can find out more at www.zaptask.co.uk.

Kind regards,

John Biddulph
Founder, ZapTask
www.zaptask.co.uk
TEXT;

    public function handle(): int
    {
        $timezone = (string) ($this->option('timezone') ?: config('app.timezone', 'UTC'));
        $sendDate = $this->resolveSendDate($timezone);
        $recipients = $this->resolveRecipients();

        if ($recipients->count() !== 100) {
            $this->error("Expected exactly 100 unique recipient emails, found {$recipients->count()}.");
            return self::FAILURE;
        }

        $user = $this->resolveUser();
        if (! $user) {
            return self::FAILURE;
        }

        $sendTimes = $this->buildSendTimes($sendDate, $timezone);
        $distribution = $this->distributionFor($recipients, $sendTimes);

        $this->table(
            ['Send time', 'Recipient count'],
            collect($distribution)
                ->groupBy(fn (array $row) => $row['send_at']->toDateTimeString())
                ->map(fn (Collection $rows, string $sendAt) => [$sendAt, $rows->count()])
                ->values()
                ->all()
        );

        if ($this->option('dry-run')) {
            $this->info('Dry run complete. No notes were created.');
            return self::SUCCESS;
        }

        $template = $this->firstOrCreateSkeletonTemplate();

        if (! $this->option('allow-duplicates') && $this->hasMatchingExistingNotes($user, $recipients, $sendTimes, $template)) {
            $this->error('Matching ZapTask outreach notes already exist for one or more recipients. Use --allow-duplicates to create another batch.');
            return self::FAILURE;
        }

        DB::transaction(function () use ($user, $template, $distribution): void {
            foreach ($distribution as $row) {
                $email = $row['email'];
                $sendAt = $row['send_at'];

                $note = Note::create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->getKey(),
                    'title' => self::TITLE,
                    'subject' => self::SUBJECT,
                    'body' => self::BODY,
                    'recipients' => $email,
                    'send_date' => $sendAt,
                    'heart_count' => 0,
                    'is_published' => true,
                    'template_id' => $template->id,
                ]);

                NoteRecipient::create([
                    'note_id' => $note->id,
                    'email' => $email,
                    'token' => (string) Str::uuid(),
                    'send_date' => $sendAt,
                ]);
            }
        });

        $this->info('Created 100 ZapTask outreach notes with no attachments.');

        return self::SUCCESS;
    }

    private function resolveSendDate(string $timezone): string
    {
        $date = $this->option('date');

        if ($date) {
            return CarbonImmutable::createFromFormat('Y-m-d', (string) $date, $timezone)->format('Y-m-d');
        }

        return CarbonImmutable::now($timezone)->addDay()->format('Y-m-d');
    }

    private function resolveRecipients(): Collection
    {
        $rawRecipients = collect($this->option('recipient') ?: []);

        $file = $this->option('recipients-file');
        if ($file) {
            if (! is_readable((string) $file)) {
                $this->error("Recipients file is not readable: {$file}");
                return collect();
            }

            $rawRecipients = $rawRecipients->merge(file((string) $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []);
        }

        return $rawRecipients
            ->flatMap(fn (string $value): array => preg_split('/[\s,;]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->map(fn (string $email): string => strtolower(trim($email)))
            ->filter(fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values();
    }

    private function resolveUser(): ?User
    {
        $userOption = $this->option('user');

        if ($userOption) {
            $user = User::query()
                ->where('email', $userOption)
                ->orWhereKey($userOption)
                ->first();

            if (! $user) {
                $this->error("No user found for '{$userOption}'.");
            }

            return $user;
        }

        $users = User::query()->limit(2)->get();
        if ($users->count() === 1) {
            return $users->first();
        }

        $this->error('Pass --user because the database does not contain exactly one user.');

        return null;
    }

    /**
     * @return array<int, CarbonImmutable>
     */
    private function buildSendTimes(string $sendDate, string $timezone): array
    {
        return [
            CarbonImmutable::createFromFormat('Y-m-d H:i', "{$sendDate} 10:00", $timezone)->setTimezone(config('app.timezone', 'UTC')),
            CarbonImmutable::createFromFormat('Y-m-d H:i', "{$sendDate} 10:10", $timezone)->setTimezone(config('app.timezone', 'UTC')),
            CarbonImmutable::createFromFormat('Y-m-d H:i', "{$sendDate} 10:20", $timezone)->setTimezone(config('app.timezone', 'UTC')),
        ];
    }

    /**
     * @param  Collection<int, string>  $recipients
     * @param  array<int, CarbonImmutable>  $sendTimes
     * @return array<int, array{email: string, send_at: CarbonImmutable}>
     */
    private function distributionFor(Collection $recipients, array $sendTimes): array
    {
        return $recipients->map(function (string $email, int $index) use ($sendTimes): array {
            $sendAt = match (true) {
                $index < 33 => $sendTimes[0],
                $index < 66 => $sendTimes[1],
                default => $sendTimes[2],
            };

            return [
                'email' => $email,
                'send_at' => $sendAt,
            ];
        })->all();
    }

    private function firstOrCreateSkeletonTemplate(): Template
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

    /**
     * @param  Collection<int, string>  $recipients
     * @param  array<int, CarbonImmutable>  $sendTimes
     */
    private function hasMatchingExistingNotes(User $user, Collection $recipients, array $sendTimes, Template $template): bool
    {
        return Note::query()
            ->where('user_id', $user->getKey())
            ->where('title', self::TITLE)
            ->where('subject', self::SUBJECT)
            ->where('body', self::BODY)
            ->where('template_id', $template->id)
            ->whereIn('send_date', collect($sendTimes)->map->toDateTimeString()->all())
            ->whereHas('recipients', fn ($query) => $query->whereIn('email', $recipients->all()))
            ->exists();
    }
}
