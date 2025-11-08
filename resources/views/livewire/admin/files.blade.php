<div class="space-y-6" x-data="{ uploading: false, progress: 0 }"
     x-on:livewire-upload-start="uploading = true; progress = 0"
     x-on:livewire-upload-finish="uploading = false; progress = 100"
     x-on:livewire-upload-error="uploading = false"
     x-on:livewire-upload-progress="progress = $event.detail.progress">

    @if($status)
        <div class="p-3 rounded {{ $publicUrl ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
            <div>{{ $status }}</div>
            @if($publicUrl)
                <div class="mt-2">
                    <a href="{{ $publicUrl }}" target="_blank" class="underline break-all">{{ $publicUrl }}</a>
                </div>
            @endif
        </div>
    @endif

    <form wire:submit.prevent="upload" class="space-y-4">
        <div>
            <label class="block text-sm font-medium">Upload file to Supabase bucket</label>
            <input type="file" wire:model="file" class="mt-1" />
            @error('file') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
            <div class="mt-3" x-show="uploading">
                <div class="h-2 w-full bg-gray-200 rounded overflow-hidden">
                    <div class="h-2 bg-blue-600" :style="`width: ${progress}%;`"></div>
                </div>
                <div class="text-xs text-gray-600 mt-1" x-text="`${progress}%`"></div>
            </div>
        </div>
        <div>
            <button type="submit"
                    class="px-4 py-2 bg-blue-600 text-white rounded disabled:opacity-50"
                    :disabled="uploading"
                    wire:loading.attr="disabled"
                    wire:target="upload,file">
                <span wire:loading.remove wire:target="upload,file">Upload</span>
                <span wire:loading wire:target="upload,file">Uploading…</span>
            </button>
        </div>
    </form>
</div>


