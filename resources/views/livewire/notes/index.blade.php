@once
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('notesCountdown', (initial = {}) => ({
                timezone: initial.timezone || 'UTC',
                now: initial.nowIso ? new Date(initial.nowIso) : new Date(),
                target: initial.nextIso ? new Date(initial.nextIso) : null,
                offset: 0,
                intervalId: null,
                eventListener: null,
                currentDisplay: '',
                countdownDisplay: '',
                nextScheduledDisplay: '',
                hasTriggeredSend: false,
                init() {
                    this.syncFromServer(initial);
                    this.eventListener = (event) => {
                        const detail = event?.detail ?? {};
                        this.syncFromServer(detail);
                    };
                    window.addEventListener('notes-countdown-sync', this.eventListener);
                },
                syncFromServer(detail = {}) {
                    if (detail.timezone) {
                        this.timezone = detail.timezone;
                    }
                    if (detail.nowIso) {
                        const newNow = new Date(detail.nowIso);
                        if (!isNaN(newNow)) {
                            this.now = newNow;
                            this.offset = this.now.getTime() - Date.now();
                        }
                    } else if (!this.offset) {
                        this.offset = this.now.getTime() - Date.now();
                    }
                    if (Object.prototype.hasOwnProperty.call(detail, 'nextIso')) {
                        this.target = detail.nextIso ? new Date(detail.nextIso) : null;
                        this.hasTriggeredSend = false;
                    }
                    this.restartInterval();
                },
                restartInterval() {
                    if (this.intervalId) {
                        clearInterval(this.intervalId);
                    }
                    this.updateDisplays();
                    this.intervalId = setInterval(() => {
                        this.now = new Date(Date.now() + this.offset);
                        this.updateDisplays();
                    }, 1000);
                },
                updateDisplays() {
                    const tz = this.timezone || 'UTC';
                    const options = { timeZone: tz, year: 'numeric', month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false };
                    this.currentDisplay = this.now.toLocaleString(undefined, options);
                    if (this.target) {
                        const targetDisplay = this.target.toLocaleString(undefined, options);
                        const diff = this.target.getTime() - this.now.getTime();
                        if (diff > 0) {
                            const totalSeconds = Math.floor(diff / 1000);
                            const days = Math.floor(totalSeconds / 86400);
                            const hours = Math.floor((totalSeconds % 86400) / 3600);
                            const minutes = Math.floor((totalSeconds % 3600) / 60);
                            const seconds = totalSeconds % 60;
                            this.countdownDisplay = `${days}d ${String(hours).padStart(2,'0')}h ${String(minutes).padStart(2,'0')}m ${String(seconds).padStart(2,'0')}s`;
                            this.nextScheduledDisplay = `Scheduled for ${targetDisplay}`;
                            this.hasTriggeredSend = false;
                        } else {
                            if (!this.hasTriggeredSend) {
                                this.hasTriggeredSend = true;
                            }
                            this.countdownDisplay = 'SENDING…';
                            this.nextScheduledDisplay = `Sending note scheduled for ${targetDisplay}`;
                        }
                    } else {
                        this.countdownDisplay = '';
                        this.nextScheduledDisplay = '';
                    }
                },
                destroy() {
                    if (this.intervalId) {
                        clearInterval(this.intervalId);
                    }
                    if (this.eventListener) {
                        window.removeEventListener('notes-countdown-sync', this.eventListener);
                    }
                },
            }));
        });
    </script>
@endonce

<div class="space-y-6" x-data="{ showCreateModal: @entangle('showCreateModal'), showEditModal: @entangle('showEditModal') }">
    @if($status)
        <div class="p-3 rounded bg-green-100 text-green-800">{{ $status }}</div>
    @endif

    @php
        $currentIso = optional($currentTime)->copy()->setTimezone('UTC')->toIso8601String();
        $nextIso = $nextDueAt ? $nextDueAt->copy()->setTimezone('UTC')->toIso8601String() : null;
        $displayTimezone = $appTimezone ?? 'UTC';
    @endphp

    <div class="flex justify-center items-center py-4">
        <div class="text-center space-y-1"
             wire:ignore
             x-data="notesCountdown({ nowIso: @js($currentIso), nextIso: @js($nextIso), timezone: @js($displayTimezone) })"
             x-cloak>
            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400" x-text="`Current time (${timezone})`"></div>
            <div class="text-2xl font-semibold text-gray-900 dark:text-white" x-text="currentDisplay"></div>
            <template x-if="target">
                <div class="text-sm text-gray-600 dark:text-gray-300">
                    Next note in:
                    <span class="font-semibold text-gray-900 dark:text-white" x-text="countdownDisplay"></span>
                </div>
            </template>
            <template x-if="target">
                <div class="text-xs text-gray-500 dark:text-gray-400" x-text="nextScheduledDisplay"></div>
            </template>
            <template x-if="!target">
                <div class="text-sm text-gray-500 dark:text-gray-400">No scheduled notes pending.</div>
            </template>
        </div>
    </div>

    <!-- Create Note Button -->
    <div class="flex justify-center items-center" style="margin: 20px;">
        <button wire:click="openCreateModal" class="px-8 py-4 text-lg font-semibold bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            Create Note
        </button>
    </div>

    <!-- Create Note Modal -->
    <div x-show="showCreateModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" x-transition>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" @click="showCreateModal = false"></div>
            <!-- Modal Content -->
            <div class="relative inline-block align-bottom bg-white dark:bg-zinc-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-5xl sm:w-full">
                <div class="bg-white dark:bg-zinc-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4 max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Create Note</h3>
                        <button @click="showCreateModal = false" wire:click="closeCreateModal" class="text-gray-400 dark:text-gray-300 hover:text-gray-600 dark:hover:text-gray-200">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <form wire:submit.prevent="save" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-900 dark:text-white">Title</label>
            <input type="text" wire:model="title" class="mt-1 w-full border rounded p-2 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white" />
            @error('title') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-900 dark:text-white">Subject</label>
            <input type="text" wire:model="subject" class="mt-1 w-full border rounded p-2 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white" />
            @error('subject') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-900 dark:text-white">Body</label>
            <textarea rows="5" wire:model="body" class="mt-1 w-full border rounded p-2 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white"></textarea>
            @error('body') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
        </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-900 dark:text-white">Send date (UTC)</label>
                <input type="datetime-local" step="600" wire:model="send_date" class="mt-1 w-full border rounded p-2 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white" />
                @error('send_date') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-900 dark:text-white">Recipient emails (comma or newline separated)</label>
                <textarea rows="3" wire:model="recipients" class="mt-1 w-full border rounded p-2 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white" placeholder="name@example.com, other@example.com"></textarea>
                @error('recipients') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-900 dark:text-white">Email template</label>
                <select wire:model="template_id" class="mt-1 w-full border rounded p-2 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white">
                    <option value="">Default</option>
                    @foreach($this->templates as $tpl)
                        <option value="{{ $tpl['id'] }}">{{ $tpl['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-900 dark:text-white">Attachments from storage</label>
                <div class="flex items-center gap-2 mt-1">
                    <button type="button" wire:click="fetchBucketFiles" class="px-3 py-1.5 border rounded bg-white dark:bg-zinc-700 text-gray-900 dark:text-white hover:bg-gray-50 dark:hover:bg-zinc-600">Load files</button>
                    <a href="{{ route('admin.files') }}" target="_blank" class="px-3 py-1.5 border rounded bg-white dark:bg-zinc-700 text-gray-900 dark:text-white hover:bg-gray-50 dark:hover:bg-zinc-600">Upload new file</a>
                    <span class="text-sm text-gray-600 dark:text-gray-300">{{ $bucketStatus }}</span>
                </div>
                <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-2 max-h-48 overflow-auto">
                    @foreach($bucketFiles as $f)
                        <div class="flex items-center justify-between border rounded p-2 bg-white dark:bg-zinc-700">
                            <div class="truncate"><span class="text-sm text-gray-900 dark:text-white">{{ $f['name'] }}</span></div>
                            <button type="button" class="text-blue-600 dark:text-blue-400 text-sm" wire:click="addAttachment('{{ $f['url'] }}', '{{ addslashes($f['name']) }}', {{ (int)($f['size'] ?? 0) }})">Attach</button>
                        </div>
                    @endforeach
                    @if(empty($bucketFiles))
                        <div class="text-sm text-gray-500 dark:text-gray-400">No files loaded.</div>
                    @endif
                </div>
                @if(!empty($attachments))
                    <div class="mt-3">
                        <div class="text-sm font-medium mb-1 text-gray-900 dark:text-white">Selected attachments</div>
                        <div class="space-y-1">
                            @foreach($attachments as $a)
                                <div class="flex items-center justify-between text-sm">
                                    <a href="{{ $a['url'] }}" target="_blank" class="text-blue-600 dark:text-blue-400 truncate">{{ $a['name'] ?? basename(parse_url($a['url'], PHP_URL_PATH) ?? '') }}</a>
                                    <button type="button" class="text-red-600 dark:text-red-400" wire:click="removeAttachment('{{ $a['url'] }}')">Remove</button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
        <div>
            <div class="flex items-center justify-between mb-1">
                <div class="text-sm font-medium text-gray-900 dark:text-white">Template preview</div>
                <button type="button" wire:click="reloadPreview" class="text-sm px-2 py-1 border rounded bg-white dark:bg-zinc-700 text-gray-900 dark:text-white hover:bg-gray-50 dark:hover:bg-zinc-600">Reload preview</button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <div class="text-xs text-gray-600 dark:text-gray-300 mb-1">Mobile</div>
                    <div class="border rounded p-2 max-w-xs overflow-hidden bg-white dark:bg-zinc-700">
                        @if($preview_html)
                            <div class="prose prose-sm max-w-none dark:prose-invert">{!! $preview_html !!}</div>
                        @else
                            <div class="prose prose-sm max-w-none dark:prose-invert">
                                <div class="text-gray-900 dark:text-gray-100 whitespace-pre-wrap">{{ \Illuminate\Support\Str::limit($body ?: 'Your email body will appear here…', 500) }}</div>
                            </div>
                        @endif
                    </div>
                </div>
                <div>
                    <div class="text-xs text-gray-600 dark:text-gray-300 mb-1">Desktop</div>
                    <div class="border rounded p-4 overflow-hidden bg-white dark:bg-zinc-700">
                        @if($preview_html)
                            <div class="prose max-w-none dark:prose-invert">{!! $preview_html !!}</div>
                        @else
                            <div class="prose max-w-none dark:prose-invert">
                                <h3 class="font-semibold text-gray-900 dark:text-white">{{ $title ?: 'Email title' }}</h3>
                                <div class="text-gray-900 dark:text-gray-100 whitespace-pre-wrap">{{ \Illuminate\Support\Str::limit($body ?: 'Your email body will appear here…', 1200) }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="flex gap-2 mt-4">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Save & Schedule</button>
            <button type="button" @click="showCreateModal = false" wire:click="closeCreateModal" class="px-4 py-2 border rounded bg-white dark:bg-zinc-700 text-gray-900 dark:text-white hover:bg-gray-50 dark:hover:bg-zinc-600">Cancel</button>
        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div wire:poll.5s>
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-semibold">Your recent notes</h2>
            <div class="inline-flex rounded-md shadow-sm overflow-hidden border border-zinc-200 dark:border-zinc-700">
                <button type="button"
                        wire:click="setFilter('scheduled')"
                        class="px-3 py-1.5 text-sm focus:outline-none transition
                               {{ $filter === 'scheduled' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200' }}">
                    Scheduled
                </button>
                <button type="button"
                        wire:click="setFilter('hearted')"
                        class="px-3 py-1.5 text-sm border-l border-zinc-200 dark:border-zinc-700 focus:outline-none transition
                               {{ $filter === 'hearted' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200' }}">
                    ❤️&nbsp;Liked
                </button>
                <button type="button"
                        wire:click="setFilter('all')"
                        class="px-3 py-1.5 text-sm border-l border-zinc-200 dark:border-zinc-700 focus:outline-none transition
                               {{ $filter === 'all' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200' }}">
                    All
                </button>
            </div>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @forelse($notes as $n)
                @php
                    $isScheduled = ($n->send_date && \Illuminate\Support\Carbon::parse($n->send_date)->isFuture()) || (($n->total_recipients ?? 0) > ($n->sent_recipients_count ?? 0));
                    $isFullySent = ($n->total_recipients ?? 0) > 0 && ($n->sent_recipients_count ?? 0) === ($n->total_recipients ?? 0);
                @endphp
                <div class="rounded p-3 border bg-white dark:bg-zinc-800 {{ $isScheduled ? 'border-yellow-400' : 'border-zinc-200 dark:border-zinc-700' }}">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="font-semibold text-gray-900 dark:text-white">{{ $n->title }}</div>
                                @if($n->subject)
                                    <div class="text-sm text-gray-700 dark:text-gray-300 mt-1">Subject: {{ $n->subject }}</div>
                                @endif
                                @php
                                    $recipientsCollection = $n->getRelationValue('recipients');
                                    $recipientsCount = $recipientsCollection ? $recipientsCollection->count() : 0;
                                    $unsentCount = $recipientsCollection ? $recipientsCollection->whereNull('sent_at')->count() : 0;
                                    $isSending = $n->send_date && \
                                        \Illuminate\Support\Carbon::parse($n->send_date)->lte(now()) &&
                                        $unsentCount > 0;
                                @endphp
                                @if($isSending)
                                    <div class="mt-1 inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-500/20 dark:text-amber-200">
                                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                        SENDING…
                                    </div>
                                @endif
                                @if($recipientsCount > 0)
                                    <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                        Recipients: <span class="break-words">{{ $recipientsCollection->pluck('email')->join(', ') }}</span>
                                    </div>
                                @endif
                                @if($n->send_date)
                                    <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                        Send at: {{ $n->send_date->format('M d, Y g:i A') }}
                                    </div>
                                @endif
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 flex items-center gap-1 flex-wrap">
                                    @if(($n->heart_count ?? 0) > 0)
                                        <span class="flex items-center gap-1">
                                            <svg class="w-4 h-4 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
                                            </svg>
                                            <span>{{ $n->heart_count }}</span>
                                        </span>
                                        @if($n->total_recipients > 0)
                                            <span>·</span>
                                        @endif
                                    @endif
                                    @if($n->total_recipients > 0)
                                        <span>
                                            Sent: {{ $n->sent_recipients_count ?? 0 }}/{{ $n->total_recipients ?? 0 }}
                                            @if($unsentCount > 0 && $n->send_date && \Illuminate\Support\Carbon::parse($n->send_date)->lte(now()))
                                                <span class="ml-1 text-amber-600 dark:text-amber-300">(processing)</span>
                                            @endif
                                        </span>
                                    @endif
                                    @if($isFullySent)
                                        <span class="inline-flex h-4 w-4 items-center justify-center rounded-full bg-green-500 text-white">✓</span>
                                    @endif
                                    @if(!empty($n->last_sent_at))
                                        <span>· Last sent: {{ \Illuminate\Support\Carbon::parse($n->last_sent_at)->diffForHumans() }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-end items-center gap-2 mt-3 pt-2 border-t border-gray-200 dark:border-zinc-700">
                            <button wire:click="startEdit('{{ $n->id }}')" class="p-2 border rounded bg-orange-300 dark:bg-orange-400 text-gray-900 dark:text-white hover:bg-orange-400 dark:hover:bg-orange-500" title="Edit">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                            </button>
                            <button wire:click="deleteNote('{{ $n->id }}')" class="p-2 bg-red-600 text-white rounded hover:bg-red-700" onclick="return confirm('Delete this note and its recipients?')" title="Delete">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                            <button wire:click="copyNote('{{ $n->id }}')" class="p-2 border rounded bg-white dark:bg-zinc-700 text-gray-900 dark:text-white hover:bg-gray-50 dark:hover:bg-zinc-600" title="Copy Note">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                            </button>
                        </div>
                </div>
            @empty
                <div class="text-gray-500">No notes yet.</div>
            @endforelse
        </div>
    </div>

    <!-- Edit Note Modal -->
    <div x-show="showEditModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" x-transition>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" @click="showEditModal = false"></div>
            <!-- Modal Content -->
            <div class="relative inline-block align-bottom bg-white dark:bg-zinc-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-5xl sm:w-full">
                <div class="bg-white dark:bg-zinc-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4 max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Edit Note</h3>
                        <button @click="showEditModal = false" wire:click="closeEditModal" class="text-gray-400 dark:text-gray-300 hover:text-gray-600 dark:hover:text-gray-200">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <form wire:submit.prevent="updateNote" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-900 dark:text-white">Title</label>
            <input type="text" wire:model="edit_title" class="mt-1 w-full border rounded p-2 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white" />
            @error('edit_title') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-900 dark:text-white">Subject</label>
            <input type="text" wire:model="edit_subject" class="mt-1 w-full border rounded p-2 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white" />
            @error('edit_subject') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-900 dark:text-white">Body</label>
            <textarea rows="5" wire:model="edit_body" class="mt-1 w-full border rounded p-2 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white"></textarea>
            @error('edit_body') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-900 dark:text-white">Send date (UTC)</label>
                <input type="datetime-local" step="600" wire:model="edit_send_date" class="mt-1 w-full border rounded p-2 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white" />
                @error('edit_send_date') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-900 dark:text-white">Recipient emails (comma or newline separated)</label>
                <textarea rows="3" wire:model="edit_recipients" class="mt-1 w-full border rounded p-2 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white" placeholder="name@example.com, other@example.com"></textarea>
                @error('edit_recipients') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-900 dark:text-white">Email template</label>
                <select wire:model="template_id" class="mt-1 w-full border rounded p-2 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white">
                    <option value="">Default</option>
                    @foreach($this->templates as $tpl)
                        <option value="{{ $tpl['id'] }}">{{ $tpl['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-900 dark:text-white">Attachments from storage</label>
                <div class="flex items-center gap-2 mt-1">
                    <button type="button" wire:click="fetchBucketFiles" class="px-3 py-1.5 border rounded bg-white dark:bg-zinc-700 text-gray-900 dark:text-white hover:bg-gray-50 dark:hover:bg-zinc-600">Load files</button>
                    <a href="{{ route('admin.files') }}" target="_blank" class="px-3 py-1.5 border rounded bg-white dark:bg-zinc-700 text-gray-900 dark:text-white hover:bg-gray-50 dark:hover:bg-zinc-600">Upload new file</a>
                    <span class="text-sm text-gray-600 dark:text-gray-300">{{ $bucketStatus }}</span>
                </div>
                <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-2 max-h-48 overflow-auto">
                    @foreach($bucketFiles as $f)
                        <div class="flex items-center justify-between border rounded p-2 bg-white dark:bg-zinc-700">
                            <div class="truncate"><span class="text-sm text-gray-900 dark:text-white">{{ $f['name'] }}</span></div>
                            <button type="button" class="text-blue-600 dark:text-blue-400 text-sm" wire:click="addAttachment('{{ $f['url'] }}', '{{ addslashes($f['name']) }}', {{ (int)($f['size'] ?? 0) }})">Attach</button>
                        </div>
                    @endforeach
                    @if(empty($bucketFiles))
                        <div class="text-sm text-gray-500 dark:text-gray-400">No files loaded.</div>
                    @endif
                </div>
                @if(!empty($attachments))
                    <div class="mt-3">
                        <div class="text-sm font-medium mb-1 text-gray-900 dark:text-white">Selected attachments</div>
                        <div class="space-y-1">
                            @foreach($attachments as $a)
                                <div class="flex items-center justify-between text-sm">
                                    <a href="{{ $a['url'] }}" target="_blank" class="text-blue-600 dark:text-blue-400 truncate">{{ $a['name'] ?? basename(parse_url($a['url'], PHP_URL_PATH) ?? '') }}</a>
                                    <button type="button" class="text-red-600 dark:text-red-400" wire:click="removeAttachment('{{ $a['url'] }}')">Remove</button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
        <div>
            <div class="flex items-center justify-between mb-1">
                <div class="text-sm font-medium text-gray-900 dark:text-white">Template preview</div>
                <button type="button" wire:click="reloadPreview" class="text-sm px-2 py-1 border rounded bg-white dark:bg-zinc-700 text-gray-900 dark:text-white hover:bg-gray-50 dark:hover:bg-zinc-600">Reload preview</button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <div class="text-xs text-gray-600 dark:text-gray-300 mb-1">Mobile</div>
                    <div class="border rounded p-2 max-w-xs overflow-hidden bg-white dark:bg-zinc-700">
                        @if($preview_html)
                            <div class="prose prose-sm max-w-none dark:prose-invert">{!! $preview_html !!}</div>
                        @else
                            <div class="prose prose-sm max-w-none dark:prose-invert">
                                <div class="text-gray-900 dark:text-gray-100 whitespace-pre-wrap">{{ \Illuminate\Support\Str::limit($edit_body ?: 'Your email body will appear here…', 500) }}</div>
                            </div>
                        @endif
                    </div>
                </div>
                <div>
                    <div class="text-xs text-gray-600 dark:text-gray-300 mb-1">Desktop</div>
                    <div class="border rounded p-4 overflow-hidden bg-white dark:bg-zinc-700">
                        @if($preview_html)
                            <div class="prose max-w-none dark:prose-invert">{!! $preview_html !!}</div>
                        @else
                            <div class="prose max-w-none dark:prose-invert">
                                <h3 class="font-semibold text-gray-900 dark:text-white">{{ $edit_title ?: 'Email title' }}</h3>
                                <div class="text-gray-900 dark:text-gray-100 whitespace-pre-wrap">{{ \Illuminate\Support\Str::limit($edit_body ?: 'Your email body will appear here…', 1200) }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="flex gap-2 mt-4">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Update Note</button>
            <button type="button" @click="showEditModal = false" wire:click="closeEditModal" class="px-4 py-2 border rounded bg-white dark:bg-zinc-700 text-gray-900 dark:text-white hover:bg-gray-50 dark:hover:bg-zinc-600">Cancel</button>
        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    </div>
</div>


