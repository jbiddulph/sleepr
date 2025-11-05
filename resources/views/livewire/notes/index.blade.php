<x-layouts.app :title="__('Notes')">
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
                <div class="border rounded p-3 flex items-start justify-between">
                    <div>
                        <div class="font-semibold">{{ $n->title }}</div>
                        <div class="text-sm text-gray-600">Hearts: {{ $n->heart_count }} · Sent at: {{ optional($n->send_date)->toDateTimeString() ?? '—' }}</div>
                    </div>
                </div>
            @empty
                <div class="text-gray-500">No notes yet.</div>
            @endforelse
        </div>
    </div>
    </div>
</div>
</x-layouts.app>


