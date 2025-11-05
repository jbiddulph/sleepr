<div class="space-y-6">
    @if($status)
        <div class="p-3 rounded bg-green-100 text-green-800">{{ $status }}</div>
    @endif

    <form wire:submit.prevent="upload" class="space-y-4">
        <div>
            <label class="block text-sm font-medium">Upload file to Supabase bucket</label>
            <input type="file" wire:model="file" class="mt-1" />
            @error('file') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
        </div>
        <div>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Upload</button>
        </div>
    </form>
</div>


