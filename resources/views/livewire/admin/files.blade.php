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

    <div class="mt-8 space-y-3">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Stored files</h2>
            <button type="button"
                    wire:click="loadFiles"
                    wire:loading.attr="disabled"
                    wire:target="loadFiles"
                    class="px-3 py-1.5 text-sm border rounded bg-white dark:bg-zinc-700 text-gray-900 dark:text-white hover:bg-gray-50 dark:hover:bg-zinc-600 disabled:opacity-50">
                <span wire:loading.remove wire:target="loadFiles">Refresh</span>
                <span wire:loading wire:target="loadFiles">Refreshing…</span>
            </button>
        </div>

        @if($loadingFiles)
            <div class="text-sm text-gray-500 dark:text-gray-400">Loading files…</div>
        @elseif(empty($files))
            <div class="text-sm text-gray-500 dark:text-gray-400">No files found in this bucket.</div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4">
                @foreach($files as $file)
                    <div class="border rounded-lg p-3 bg-white dark:bg-zinc-800 shadow-sm">
                        <div class="text-sm font-medium text-gray-900 dark:text-white break-words">{{ $file['name'] }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 break-words mt-1">{{ $file['path'] }}</div>
                        @if(!empty($file['url']))
                            <div class="mt-2">
                                <a href="{{ $file['url'] }}" target="_blank"
                                   class="inline-flex items-center text-sm text-blue-600 dark:text-blue-400 hover:underline">
                                    View
                                </a>
                            </div>
                        @endif
                    </div>

<script>
    window.addEventListener('admin-files-debug', event => {
        console.log('[admin.files]', event.detail.event, event.detail.context);
    });
</script>
                @endforeach
            </div>
        @endif
    </div>
</div>


