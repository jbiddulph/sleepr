<div class="space-y-6">
    @if($status)
        <div class="p-3 rounded bg-green-100 text-green-800">{{ $status }}</div>
    @endif

    <form wire:submit.prevent="save" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium">Name</label>
                <input type="text" wire:model="name" class="mt-1 w-full border rounded p-2" />
                @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">Slug</label>
                <input type="text" wire:model="slug" class="mt-1 w-full border rounded p-2" placeholder="auto-generated from name if blank" />
                @error('slug') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium">HTML (use {{'{{title}}'}}, {{'{{body}}'}}, {{'{{heart_url}}'}})</label>
            <textarea rows="12" wire:model="html" class="mt-1 w-full border rounded p-2 font-mono text-sm"></textarea>
            @error('html') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="flex items-center gap-2">
            <label class="text-sm">Active</label>
            <input type="checkbox" wire:model="is_active" class="h-4 w-4" />
        </div>
        <div class="flex items-center gap-2">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">{{ $edit_id ? 'Update' : 'Create' }} Template</button>
            @if($edit_id)
                <button type="button" wire:click="cancel" class="px-4 py-2 border rounded">Cancel</button>
            @endif
        </div>
    </form>

    <div>
        <h2 class="text-lg font-semibold mb-2">Templates</h2>
        <div class="border rounded divide-y">
            @forelse($templates as $tpl)
                <div class="p-3 flex items-start justify-between gap-3">
                    <div>
                        <div class="font-medium">{{ $tpl->name }} <span class="text-xs text-gray-500">({{ $tpl->slug }})</span></div>
                        <div class="text-xs text-gray-600">{{ $tpl->is_active ? 'Active' : 'Inactive' }}</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button wire:click="edit('{{ $tpl->id }}')" class="px-3 py-1.5 border rounded">Edit</button>
                        <button wire:click="delete('{{ $tpl->id }}')" class="px-3 py-1.5 bg-red-600 text-white rounded" onclick="return confirm('Delete this template?')">Delete</button>
                    </div>
                </div>
            @empty
                <div class="p-3 text-gray-500">No templates yet.</div>
            @endforelse
        </div>
        <div class="mt-3">{{ $templates->links() }}</div>
    </div>
</div>


