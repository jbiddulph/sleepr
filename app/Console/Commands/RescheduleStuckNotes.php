<?php

namespace App\Console\Commands;

use App\Services\RescheduleStuckNotes as RescheduleStuckNotesService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class RescheduleStuckNotes extends Command
{
    protected $signature = 'notes:reschedule-stuck
        {--at=2026-09-21 08:10 : Send datetime to apply to stuck unsent notes}
        {--timezone= : Timezone for interpreting --at; defaults to app timezone}
        {--dry-run : Show matching notes without writing changes}';

    protected $description = 'Reschedule overdue unsent notes (Sent: 0/1 processing) to a new send time.';

    public function handle(RescheduleStuckNotesService $rescheduler): int
    {
        $timezone = (string) ($this->option('timezone') ?: config('app.timezone', 'UTC'));
        $sendAt = $this->resolveSendAt($timezone);

        if (! $sendAt) {
            return self::FAILURE;
        }

        $result = $rescheduler->handle($sendAt, (bool) $this->option('dry-run'));

        if ($result['rows']->isNotEmpty()) {
            $this->table(
                ['Title', 'Recipient', 'Previous send at'],
                $result['rows']->map(fn (array $row): array => [
                    $row['title'],
                    $row['email'],
                    $row['previous_send_at'] ?? '—',
                ])->all()
            );
        }

        $verb = $this->option('dry-run') ? 'Would reschedule' : 'Rescheduled';
        $this->info(sprintf(
            '%s %d note(s) / %d recipient(s) to %s (%s). Cleared %d queued send job(s).',
            $verb,
            $result['notes'],
            $result['recipients'],
            $sendAt->toDateTimeString(),
            $timezone,
            $result['jobs_cleared']
        ));

        return self::SUCCESS;
    }

    private function resolveSendAt(string $timezone): ?CarbonImmutable
    {
        $at = trim((string) $this->option('at'));

        try {
            $sendAt = CarbonImmutable::parse($at, $timezone)->setTimezone(config('app.timezone', 'UTC'));
        } catch (\Throwable $e) {
            $this->error("Could not parse send time '{$at}' in timezone '{$timezone}'.");

            return null;
        }

        if ($sendAt->minute % 10 !== 0 || $sendAt->second !== 0) {
            $this->error('Send time must be in 10-minute increments (00, 10, 20, 30, 40, 50).');

            return null;
        }

        return $sendAt;
    }
}
