<div class="space-y-6">
    @if($status)
        <div class="p-3 rounded bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200">{{ $status }}</div>
    @endif

    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <a href="{{ route('prospects.index') }}" class="text-sm text-blue-600 hover:underline" wire:navigate>← Back to prospects</a>
            <h1 class="text-2xl font-bold mt-2">{{ $prospect->agency_name }}</h1>
            <p class="text-zinc-600 dark:text-zinc-300">{{ $prospect->town }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($prospect->website)
                <a href="{{ $prospect->website }}" target="_blank" rel="noopener" class="px-3 py-1 border rounded">Website</a>
            @endif
            @if($prospect->contact_page_url)
                <a href="{{ $prospect->contact_page_url }}" target="_blank" rel="noopener" class="px-3 py-1 border rounded">Contact page</a>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="space-y-6">
            <div class="border rounded-lg p-4 space-y-4 dark:border-zinc-700">
                <h2 class="text-lg font-semibold">Outreach settings</h2>

                <form wire:submit.prevent="saveProspect" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Outreach status</label>
                        <select wire:model="outreach_status" class="w-full border rounded p-2 bg-white dark:bg-zinc-700">
                            <option value="pending">Pending</option>
                            <option value="reviewing">Reviewing</option>
                            <option value="ready">Ready</option>
                            <option value="contacted">Contacted</option>
                            <option value="replied">Replied</option>
                            <option value="not_interested">Not interested</option>
                            <option value="no_email">No email</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Selected email</label>
                        <select wire:model="selected_email" class="w-full border rounded p-2 bg-white dark:bg-zinc-700">
                            <option value="">Choose an email...</option>
                            @foreach($emailOptions as $email)
                                <option value="{{ $email }}">{{ $email }}</option>
                            @endforeach
                        </select>
                        @error('selected_email') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        Save prospect
                    </button>
                </form>
            </div>

            <div class="border rounded-lg p-4 space-y-3 dark:border-zinc-700">
                <h2 class="text-lg font-semibold">Discovered emails</h2>

                @if(!empty($prospect->best_emails))
                    <div>
                        <h3 class="text-sm font-medium text-zinc-500 mb-1">Best matches</h3>
                        <ul class="space-y-1 text-sm">
                            @foreach($prospect->best_emails as $email)
                                <li><button type="button" wire:click="selectEmail(@js($email))" class="text-blue-600 hover:underline">{{ $email }}</button></li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if(!empty($prospect->other_business_emails))
                    <div>
                        <h3 class="text-sm font-medium text-zinc-500 mb-1">Other business emails</h3>
                        <ul class="space-y-1 text-sm">
                            @foreach($prospect->other_business_emails as $email)
                                <li><button type="button" wire:click="selectEmail(@js($email))" class="text-blue-600 hover:underline">{{ $email }}</button></li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if(empty($prospect->best_emails) && empty($prospect->other_business_emails))
                    <p class="text-sm text-zinc-500">No emails were found for this prospect.</p>
                @endif

                <p class="text-xs text-zinc-500">{{ $prospect->review_status }}</p>
            </div>
        </div>

        <div class="space-y-6">
            <div class="border rounded-lg p-4 space-y-4 dark:border-zinc-700">
                <h2 class="text-lg font-semibold">Add activity</h2>

                <div>
                    <label class="block text-sm font-medium mb-1">Outreach template</label>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <select wire:model.live="template_id" class="w-full border rounded p-2 bg-white dark:bg-zinc-700">
                            <option value="">Choose a template...</option>
                            @foreach($templates as $template)
                                <option value="{{ $template->id }}">{{ $template->name }}</option>
                            @endforeach
                        </select>
                        <button
                            type="button"
                            wire:click="applySelectedTemplate"
                            @disabled(! $template_id)
                            class="px-4 py-2 border rounded hover:bg-zinc-100 dark:hover:bg-zinc-800 disabled:opacity-50"
                        >
                            Apply template
                        </button>
                    </div>
                    <p class="text-xs text-zinc-500 mt-2">
                        Your template choice is remembered as you move between prospects.
                    </p>
                </div>

                <form wire:submit.prevent="addNote" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Type</label>
                        <select wire:model.live="note_type" class="w-full border rounded p-2 bg-white dark:bg-zinc-700">
                            <option value="note">Note</option>
                            <option value="email_draft">Email draft</option>
                            <option value="email_sent">Email sent</option>
                            <option value="call">Call log</option>
                        </select>
                    </div>

                    @if(in_array($note_type, ['email_draft', 'email_sent']))
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium mb-1">To</label>
                                <input type="email" wire:model="email_to" class="w-full border rounded p-2 bg-white dark:bg-zinc-700" />
                                @error('email_to') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">From</label>
                                <input type="email" wire:model="email_from" class="w-full border rounded p-2 bg-white dark:bg-zinc-700" />
                                @error('email_from') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">Subject</label>
                            <input type="text" wire:model="subject" class="w-full border rounded p-2 bg-white dark:bg-zinc-700" />
                            @error('subject') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium mb-1">
                            {{ in_array($note_type, ['email_draft', 'email_sent']) ? 'Email body' : 'Note' }}
                        </label>
                        <textarea rows="6" wire:model="body" class="w-full border rounded p-2 bg-white dark:bg-zinc-700"></textarea>
                        @error('body') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        Save activity
                    </button>
                </form>
            </div>

            <div class="border rounded-lg p-4 space-y-4 dark:border-zinc-700">
                <h2 class="text-lg font-semibold">Activity log</h2>

                @forelse($notes as $note)
                    <div class="border-b border-zinc-200 dark:border-zinc-700 pb-4 last:border-b-0 last:pb-0">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs px-2 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800">{{ $note->typeLabel() }}</span>
                            <span class="text-xs text-zinc-500">{{ $note->created_at?->diffForHumans() }}</span>
                        </div>
                        @if($note->subject)
                            <p class="font-medium mt-2">{{ $note->subject }}</p>
                        @endif
                        @if($note->email_to)
                            <p class="text-sm text-zinc-600 dark:text-zinc-300 mt-1">To: {{ $note->email_to }}</p>
                        @endif
                        @if($note->scheduled_send_at)
                            <p class="text-sm text-zinc-600 dark:text-zinc-300 mt-1">
                                Scheduled: {{ $note->scheduled_send_at->format('M j, Y g:i A') }} UTC
                            </p>
                        @endif
                        <p class="text-sm mt-2 whitespace-pre-wrap">{{ $note->body }}</p>
                        <p class="text-xs text-zinc-500 mt-2">
                            {{ $note->author?->name ?? 'Unknown user' }}
                            @if($note->sent_at)
                                · sent {{ $note->sent_at->diffForHumans() }}
                            @endif
                        </p>
                    </div>
                @empty
                    <p class="text-sm text-zinc-500">No notes or emails logged yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
