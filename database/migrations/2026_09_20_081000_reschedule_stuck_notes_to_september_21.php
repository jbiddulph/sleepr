<?php

use App\Services\RescheduleStuckNotes;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Reschedule overdue unsent notes so they send on Monday 21 September 2026 at 08:10.
     */
    public function up(): void
    {
        $sendAt = CarbonImmutable::parse('2026-09-21 08:10:00', config('app.timezone', 'UTC'));

        app(RescheduleStuckNotes::class)->handle($sendAt);
    }

    public function down(): void
    {
        // One-time data repair; previous send times are not restored.
    }
};
