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
            <label class="block text-sm font-medium">Body</label>
            <textarea rows="5" wire:model="body" class="mt-1 w-full border rounded p-2"></textarea>
            @error('body') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium">Send date (UTC)</label>
                <input type="datetime-local" wire:model="send_date" class="mt-1 w-full border rounded p-2" />
                @error('send_date') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">Recipient emails (comma or newline separated)</label>
                <textarea rows="3" wire:model="recipients" class="mt-1 w-full border rounded p-2" placeholder="name@example.com, other@example.com"></textarea>
                @error('recipients') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
        <div>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Save & Schedule</button>
        </div>
    </form>

    <div>
        <h2 class="text-lg font-semibold mb-2">Your recent notes</h2>
        <div class="space-y-3">
            @forelse($notes as $n)
                <div class="border rounded p-3">
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
                                <input type="datetime-local" wire:model.defer="edit_send_date" class="mt-1 w-full border rounded p-2" />
                                @error('edit_send_date') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div class="flex gap-2">
                                <button wire:click="updateNote" class="px-3 py-1.5 bg-blue-600 text-white rounded">Save</button>
                                <button wire:click="cancelEdit" type="button" class="px-3 py-1.5 border rounded">Cancel</button>
                                <button wire:click="deleteNote('{{ $n->id }}')" type="button" class="px-3 py-1.5 bg-red-600 text-white rounded" onclick="return confirm('Delete this note and its recipients?')">Delete</button>
                            </div>
                        </div>
                    @else
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="font-semibold">{{ $n->title }}</div>
                                <div class="text-sm text-gray-600">Hearts: {{ $n->heart_count }} · Send at: {{ $n->send_date ?? '—' }}</div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button wire:click="startEdit('{{ $n->id }}')" class="px-3 py-1.5 border rounded">Edit</button>
                                <button wire:click="deleteNote('{{ $n->id }}')" class="px-3 py-1.5 bg-red-600 text-white rounded" onclick="return confirm('Delete this note and its recipients?')">Delete</button>
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


