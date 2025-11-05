<div class="space-y-6">
    @if($status)
        <div class="p-3 rounded bg-green-100 text-green-800">{{ $status }}</div>
    @endif

    <form wire:submit.prevent="save" class="space-y-4">
        <div>
            <label class="block text-sm font-medium">Title</label>
            <input type="text" wire:model="title" class="mt-1 w-full border rounded p-2" />
            @error('title') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium">Subject (optional)</label>
            <input type="text" wire:model="subject" class="mt-1 w-full border rounded p-2" />
            @error('subject') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium">Body</label>
            <textarea rows="5" wire:model="body" class="mt-1 w-full border rounded p-2"></textarea>
            @error('body') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium">Send date (UTC)</label>
                <input type="datetime-local" step="600" wire:model="send_date" class="mt-1 w-full border rounded p-2" />
                @error('send_date') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">Recipient emails (comma or newline separated)</label>
                <textarea rows="3" wire:model="recipients" class="mt-1 w-full border rounded p-2" placeholder="name@example.com, other@example.com"></textarea>
                @error('recipients') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium">Email template</label>
                <select wire:model="template_id" class="mt-1 w-full border rounded p-2">
                    <option value="">Default</option>
                    @foreach($this->templates as $tpl)
                        <option value="{{ $tpl['id'] }}">{{ $tpl['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium">Attachments from storage</label>
                <div class="flex items-center gap-2 mt-1">
                    <button type="button" wire:click="fetchBucketFiles" class="px-3 py-1.5 border rounded">Load files</button>
                    <a href="{{ route('admin.files') }}" target="_blank" class="px-3 py-1.5 border rounded">Upload new file</a>
                    <span class="text-sm text-gray-600">{{ $bucketStatus }}</span>
                </div>
                <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-2 max-h-48 overflow-auto">
                    @foreach($bucketFiles as $f)
                        <div class="flex items-center justify-between border rounded p-2">
                            <div class="truncate"><span class="text-sm">{{ $f['name'] }}</span></div>
                            <button type="button" class="text-blue-600 text-sm" wire:click="addAttachment('{{ $f['url'] }}', '{{ addslashes($f['name']) }}', {{ (int)($f['size'] ?? 0) }})">Attach</button>
                        </div>
                    @endforeach
                    @if(empty($bucketFiles))
                        <div class="text-sm text-gray-500">No files loaded.</div>
                    @endif
                </div>
                @if(!empty($attachments))
                    <div class="mt-3">
                        <div class="text-sm font-medium mb-1">Selected attachments</div>
                        <div class="space-y-1">
                            @foreach($attachments as $a)
                                <div class="flex items-center justify-between text-sm">
                                    <a href="{{ $a['url'] }}" target="_blank" class="text-blue-600 truncate">{{ $a['name'] ?? basename(parse_url($a['url'], PHP_URL_PATH) ?? '') }}</a>
                                    <button type="button" class="text-red-600" wire:click="removeAttachment('{{ $a['url'] }}')">Remove</button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
        <div>
            <div class="flex items-center justify-between mb-1">
                <div class="text-sm font-medium">Template preview</div>
                <button type="button" wire:click="reloadPreview" class="text-sm px-2 py-1 border rounded">Reload preview</button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <div class="text-xs text-gray-600 mb-1">Mobile</div>
                    <div class="border rounded p-2 max-w-xs overflow-hidden">
                        @if($preview_html)
                            <div class="prose prose-sm max-w-none">{!! $preview_html !!}</div>
                        @else
                            <div class="prose prose-sm max-w-none">
                                <div class="text-gray-900 whitespace-pre-wrap">{{ \Illuminate\Support\Str::limit($body ?: 'Your email body will appear here…', 500) }}</div>
                            </div>
                        @endif
                    </div>
                </div>
                <div>
                    <div class="text-xs text-gray-600 mb-1">Desktop</div>
                    <div class="border rounded p-4 overflow-hidden">
                        @if($preview_html)
                            <div class="prose max-w-none">{!! $preview_html !!}</div>
                        @else
                            <div class="prose max-w-none">
                                <h3 class="font-semibold">{{ $title ?: 'Email title' }}</h3>
                                <div class="text-gray-900 whitespace-pre-wrap">{{ \Illuminate\Support\Str::limit($body ?: 'Your email body will appear here…', 1200) }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Save & Schedule</button>
        </div>
    </form>

    <div wire:poll.5s>
        <h2 class="text-lg font-semibold mb-2">Your recent notes</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @forelse($notes as $n)
                @php
                    $isScheduled = ($n->send_date && \Illuminate\Support\Carbon::parse($n->send_date)->isFuture()) || (($n->total_recipients ?? 0) > ($n->sent_recipients_count ?? 0));
                    $isFullySent = ($n->total_recipients ?? 0) > 0 && ($n->sent_recipients_count ?? 0) === ($n->total_recipients ?? 0);
                @endphp
                <div class="rounded p-3 border {{ $isScheduled ? 'border-yellow-400' : 'border-zinc-200' }}">
                    @if($edit_note_id === $n->id)
                        <div class="space-y-3">
                            <div>
                                <label class="block text-sm font-medium">Title</label>
                                <input type="text" wire:model.defer="edit_title" class="mt-1 w-full border rounded p-2" />
                                @error('edit_title') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm">Body</label>
                                <textarea rows="4" wire:model.defer="edit_body" class="mt-1 w-full border rounded p-2"></textarea>
                                @error('edit_body') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm">Send date (UTC)</label>
                                <input type="datetime-local" step="600" wire:model.defer="edit_send_date" class="mt-1 w-full border rounded p-2" />
                                @error('edit_send_date') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium">Email template</label>
                                    <select wire:model="template_id" class="mt-1 w-full border rounded p-2">
                                        <option value="">Default</option>
                                        @foreach($this->templates as $tpl)
                                            <option value="{{ $tpl['id'] }}">{{ $tpl['name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium">Attachments from storage</label>
                                    <div class="flex items-center gap-2 mt-1">
                                        <button type="button" wire:click="fetchBucketFiles" class="px-3 py-1.5 border rounded">Load files</button>
                                        <a href="{{ route('admin.files') }}" target="_blank" class="px-3 py-1.5 border rounded">Upload new file</a>
                                        <span class="text-sm text-gray-600">{{ $bucketStatus }}</span>
                                    </div>
                                    <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-2 max-h-48 overflow-auto">
                                        @foreach($bucketFiles as $f)
                                            <div class="flex items-center justify-between border rounded p-2">
                                                <div class="truncate"><span class="text-sm">{{ $f['name'] }}</span></div>
                                                <button type="button" class="text-blue-600 text-sm" wire:click="addAttachment('{{ $f['url'] }}', '{{ addslashes($f['name']) }}', {{ (int)($f['size'] ?? 0) }})">Attach</button>
                                            </div>
                                        @endforeach
                                        @if(empty($bucketFiles))
                                            <div class="text-sm text-gray-500">No files loaded.</div>
                                        @endif
                                    </div>
                                    @if(!empty($attachments))
                                        <div class="mt-3">
                                            <div class="text-sm font-medium mb-1">Selected attachments</div>
                                            <div class="space-y-1">
                                                @foreach($attachments as $a)
                                                    <div class="flex items-center justify-between text-sm">
                                                        <a href="{{ $a['url'] }}" target="_blank" class="text-blue-600 truncate">{{ $a['name'] ?? basename(parse_url($a['url'], PHP_URL_PATH) ?? '') }}</a>
                                                        <button type="button" class="text-red-600" wire:click="removeAttachment('{{ $a['url'] }}')">Remove</button>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button wire:click="updateNote" class="px-2 py-1 text-sm bg-blue-600 text-white rounded">Save</button>
                                <button wire:click="cancelEdit" type="button" class="px-2 py-1 text-sm border rounded">Cancel</button>
                                <button wire:click="deleteNote('{{ $n->id }}')" type="button" class="px-2 py-1 text-sm bg-red-600 text-white rounded" onclick="return confirm('Delete this note and its recipients?')">Delete</button>
                            </div>
                        </div>
                    @else
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="font-semibold">{{ $n->title }}</div>
                                <div class="text-sm text-gray-600">Hearts: {{ $n->heart_count }} · Send at: {{ $n->send_date ?? '—' }}</div>
                                <div class="text-xs text-gray-500 mt-0.5 flex items-center gap-1">
                                    <span>Sent: {{ $n->sent_recipients_count ?? 0 }}/{{ $n->total_recipients ?? 0 }}</span>
                                    @if($isFullySent)
                                        <span class="inline-flex h-4 w-4 items-center justify-center rounded-full bg-green-500 text-white">✓</span>
                                    @endif
                                    @if(!empty($n->last_sent_at))
                                        <span>· Last sent: {{ \Illuminate\Support\Carbon::parse($n->last_sent_at)->diffForHumans() }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button wire:click="startEdit('{{ $n->id }}')" class="px-2 py-1 text-sm border rounded">Edit</button>
                                <button wire:click="deleteNote('{{ $n->id }}')" class="px-2 py-1 text-sm bg-red-600 text-white rounded" onclick="return confirm('Delete this note and its recipients?')">Delete</button>
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <div class="text-gray-500">No notes yet.</div>
            @endforelse
        </div>
    </div>
    </div>
</div>


