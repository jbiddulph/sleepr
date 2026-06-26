<div class="space-y-6">
    @if($status)
        <div class="p-3 rounded bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200">{{ $status }}</div>
    @endif

    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold">Estate Agent Prospects</h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-300">Browse imported prospects, pick emails, and log outreach.</p>
        </div>
        <div class="flex flex-wrap gap-2 text-sm">
            <span class="px-2 py-1 rounded bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200">
                Ready: {{ $counts['ready'] ?? 0 }}
            </span>
            <span class="px-2 py-1 rounded bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">
                Reviewing: {{ $counts['reviewing'] ?? 0 }}
            </span>
            <span class="px-2 py-1 rounded bg-zinc-200 text-zinc-800 dark:bg-zinc-700 dark:text-zinc-200">
                No email: {{ $counts['no_email'] ?? 0 }}
            </span>
        </div>
    </div>

    <div class="border rounded-lg p-4 space-y-4 dark:border-zinc-700">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-lg font-semibold">Outreach template</h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-300">
                    Pick one template for all prospects. Use merge tags like
                    <code class="text-xs">@{{agency_name}}</code>,
                    <code class="text-xs">@{{town}}</code>,
                    <code class="text-xs">@{{sender_name}}</code>.
                </p>
            </div>
            <button type="button" wire:click="openTemplateForm" class="px-3 py-2 border rounded hover:bg-zinc-100 dark:hover:bg-zinc-800">
                New template
            </button>
        </div>

        <div class="grid grid-cols-1 gap-3 xl:grid-cols-2">
            <div>
                <label class="block text-sm font-medium mb-1">First send time (UTC)</label>
                <input
                    type="datetime-local"
                    step="600"
                    wire:model.live="bulk_send_date"
                    class="w-full border rounded p-2 bg-white dark:bg-zinc-700"
                />
                @error('bulk_send_date') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                <p class="text-xs text-zinc-500 mt-1">Use 10-minute increments. Scheduled emails appear in Notes and send automatically.</p>
            </div>

            <div class="space-y-3">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model.live="bulk_stagger" class="rounded" />
                    Spread sends in groups of 33 every 10 minutes
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model.live="onlyReadyForBulk" class="rounded" />
                    Only prospects marked ready
                </label>
                @if($this->bulkSendWindow)
                    <p class="text-sm text-zinc-600 dark:text-zinc-300">
                        Send window: <span class="font-medium">{{ $this->bulkSendWindow }}</span>
                    </p>
                @endif
            </div>
        </div>

        <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
            <div class="flex-1">
                <label class="block text-sm font-medium mb-1">Template</label>
                <select wire:model.live="template_id" class="w-full border rounded p-2 bg-white dark:bg-zinc-700">
                    <option value="">Choose a template...</option>
                    @foreach($templates as $template)
                        <option value="{{ $template->id }}">{{ $template->name }}</option>
                    @endforeach
                </select>
            </div>

            <button
                type="button"
                wire:click="bulkCreateDrafts"
                wire:confirm="Schedule outreach emails for matching prospects that have an email address?"
                @disabled(! $template_id || ! $bulk_send_date)
                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
            >
                Schedule emails ({{ $this->bulkTargetCount }})
            </button>
        </div>

        @if($templates->isNotEmpty())
            <div class="flex flex-wrap gap-2">
                @foreach($templates as $template)
                    <button
                        type="button"
                        wire:click="openTemplateForm('{{ $template->id }}')"
                        class="px-2 py-1 text-xs border rounded hover:bg-zinc-100 dark:hover:bg-zinc-800"
                    >
                        Edit {{ $template->name }}
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    @if($showTemplateForm)
        <div class="border rounded-lg p-4 space-y-4 dark:border-zinc-700">
            <h3 class="text-lg font-semibold">{{ $edit_template_id ? 'Edit template' : 'New template' }}</h3>
            <form wire:submit.prevent="saveTemplate" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Name</label>
                    <input type="text" wire:model="template_name" class="w-full border rounded p-2 bg-white dark:bg-zinc-700" />
                    @error('template_name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Subject</label>
                    <input type="text" wire:model="template_subject" class="w-full border rounded p-2 bg-white dark:bg-zinc-700" />
                    @error('template_subject') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Body</label>
                    <textarea rows="8" wire:model="template_body" class="w-full border rounded p-2 bg-white dark:bg-zinc-700"></textarea>
                    @error('template_body') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Save template</button>
                    <button type="button" wire:click="$set('showTemplateForm', false)" class="px-4 py-2 border rounded">Cancel</button>
                </div>
            </form>
        </div>
    @endif

    <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
        <input
            type="text"
            wire:model.live.debounce.400ms="query"
            class="border rounded p-2 w-full lg:w-80 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white"
            placeholder="Search agency, town, or email..."
        />

        <select
            wire:model.live="statusFilter"
            class="border rounded p-2 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white"
        >
            <option value="all">All statuses</option>
            <option value="ready">Ready</option>
            <option value="reviewing">Reviewing</option>
            <option value="pending">Pending</option>
            <option value="contacted">Contacted</option>
            <option value="replied">Replied</option>
            <option value="not_interested">Not interested</option>
            <option value="no_email">No email</option>
        </select>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full border divide-y divide-zinc-200 dark:divide-zinc-800">
            <thead class="bg-zinc-50 dark:bg-zinc-900">
                <tr>
                    <th class="p-2 text-left text-sm font-medium">Agency</th>
                    <th class="p-2 text-left text-sm font-medium">Town</th>
                    <th class="p-2 text-left text-sm font-medium">Best email</th>
                    <th class="p-2 text-left text-sm font-medium">Status</th>
                    <th class="p-2 text-left text-sm font-medium">Review</th>
                    <th class="p-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @forelse($prospects as $prospect)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-900/50">
                        <td class="p-2 font-medium">{{ $prospect->agency_name }}</td>
                        <td class="p-2">{{ $prospect->town }}</td>
                        <td class="p-2">
                            @if($prospect->selected_email)
                                {{ $prospect->selected_email }}
                            @elseif(!empty($prospect->best_emails))
                                {{ $prospect->best_emails[0] }}
                            @else
                                <span class="text-zinc-500">—</span>
                            @endif
                        </td>
                        <td class="p-2">
                            <span class="px-2 py-0.5 rounded text-xs bg-zinc-100 dark:bg-zinc-800">
                                {{ $prospect->outreachStatusLabel() }}
                            </span>
                        </td>
                        <td class="p-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $prospect->review_status ?? '—' }}</td>
                        <td class="p-2 text-right">
                            <a
                                href="{{ route('prospects.show', $prospect) }}"
                                class="px-3 py-1 border rounded hover:bg-zinc-100 dark:hover:bg-zinc-700"
                                wire:navigate
                            >
                                Open
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-6 text-center text-zinc-500">No prospects match your filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $prospects->links() }}
    </div>
</div>
