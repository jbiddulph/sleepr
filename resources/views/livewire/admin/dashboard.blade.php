<div class="space-y-6">
    <h1 class="text-2xl font-bold">Admin Dashboard</h1>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="p-4 bg-white dark:bg-zinc-900 rounded shadow">
            <div class="text-zinc-500 text-sm">Total Notes</div>
            <div class="text-2xl font-semibold">{{ $totals['notes'] }}</div>
        </div>
        <div class="p-4 bg-white dark:bg-zinc-900 rounded shadow">
            <div class="text-zinc-500 text-sm">Recipients</div>
            <div class="text-2xl font-semibold">{{ $totals['recipients'] }}</div>
        </div>
        <div class="p-4 bg-white dark:bg-zinc-900 rounded shadow">
            <div class="text-zinc-500 text-sm">Sent</div>
            <div class="text-2xl font-semibold">{{ $totals['sent'] }}</div>
        </div>
        <div class="p-4 bg-white dark:bg-zinc-900 rounded shadow">
            <div class="text-zinc-500 text-sm">Hearts</div>
            <div class="text-2xl font-semibold">{{ $totals['hearted'] }}</div>
        </div>
    </div>

    <div>
        <h2 class="text-xl font-semibold mb-3">Recent Hearts</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full border divide-y divide-zinc-200 dark:divide-zinc-800">
                <thead class="bg-zinc-50 dark:bg-zinc-900">
                    <tr>
                        <th class="text-left p-2 text-sm font-medium">Time</th>
                        <th class="text-left p-2 text-sm font-medium">Note</th>
                        <th class="text-left p-2 text-sm font-medium">Recipient</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse($hearted as $row)
                        <tr>
                            <td class="p-2 text-sm">{{ \Illuminate\Support\Carbon::parse($row->hearted_at)->diffForHumans() }}</td>
                            <td class="p-2 text-sm">{{ $row->note_title }}</td>
                            <td class="p-2 text-sm">{{ $row->email }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="p-4 text-zinc-500">No hearts yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>


