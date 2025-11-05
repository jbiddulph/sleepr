<div class="space-y-6" x-data="{ showModal: @entangle('showCreateModal') }">
    @if($status)
        <div class="p-3 rounded bg-green-100 text-green-800">{{ $status }}</div>
    @endif

    <!-- Create Template Button -->
    <div class="flex justify-center items-center" style="margin: 20px;">
        <button wire:click="openCreateModal" class="px-8 py-4 text-lg font-semibold bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            Create Template
        </button>
    </div>

    <!-- Modal (only show when creating, not editing) -->
    <div x-show="showModal && !@js($edit_id)" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="showModal = false"></div>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-6xl sm:w-full" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Create Template</h3>
                        <button @click="showModal = false" wire:click="closeCreateModal" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <form wire:submit.prevent="save" class="space-y-4">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
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
            <label class="block text-sm font-medium">HTML (use &#123;&#123;title&#125;&#125;, &#123;&#123;body&#125;&#125;, &#123;&#123;heart_url&#125;&#125;)</label>
            <textarea rows="12" wire:model.lazy="html" class="mt-1 w-full border rounded p-2 font-mono text-sm"></textarea>
            @error('html') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="flex items-center gap-2">
            <label class="text-sm">Active</label>
            <input type="checkbox" wire:model="is_active" class="h-4 w-4" />
        </div>
        <div class="flex items-center gap-2 mt-4">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Create Template</button>
            <button type="button" @click="showModal = false" wire:click="closeCreateModal" class="px-4 py-2 border rounded">Cancel</button>
        </div>
                    </form>

                    <div class="mt-6">
                        <h3 class="text-sm font-medium mb-2">Live preview</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div class="text-xs text-gray-600 mb-1">Mobile</div>
                                <div class="border rounded p-2 max-w-xs overflow-hidden">
                                    @if(!empty($preview))
                                        <div class="prose prose-sm max-w-none">{!! $preview !!}</div>
                                    @else
                                        <div class="text-sm text-gray-500">Start typing your HTML above…</div>
                                    @endif
                                </div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-600 mb-1">Desktop</div>
                                <div class="border rounded p-4 overflow-hidden">
                                    @if(!empty($preview))
                                        <div class="prose max-w-none">{!! $preview !!}</div>
                                    @else
                                        <div class="text-sm text-gray-500">Start typing your HTML above…</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Form (inline, not in modal) -->
    @if($edit_id)
        <div class="border rounded p-4 bg-gray-50">
            <h3 class="text-lg font-medium mb-4">Edit Template</h3>
            <form wire:submit.prevent="save" class="space-y-4">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
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
                    <label class="block text-sm font-medium">HTML (use &#123;&#123;title&#125;&#125;, &#123;&#123;body&#125;&#125;, &#123;&#123;heart_url&#125;&#125;)</label>
                    <textarea rows="12" wire:model.lazy="html" class="mt-1 w-full border rounded p-2 font-mono text-sm"></textarea>
                    @error('html') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-sm">Active</label>
                    <input type="checkbox" wire:model="is_active" class="h-4 w-4" />
                </div>
                <div class="flex items-center gap-2 mt-4">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Update Template</button>
                    <button type="button" wire:click="cancel" class="px-4 py-2 border rounded">Cancel</button>
                </div>
            </form>
            <div class="mt-6">
                <h3 class="text-sm font-medium mb-2">Live preview</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <div class="text-xs text-gray-600 mb-1">Mobile</div>
                        <div class="border rounded p-2 max-w-xs overflow-hidden">
                            @if(!empty($preview))
                                <div class="prose prose-sm max-w-none">{!! $preview !!}</div>
                            @else
                                <div class="text-sm text-gray-500">Start typing your HTML above…</div>
                            @endif
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-600 mb-1">Desktop</div>
                        <div class="border rounded p-4 overflow-hidden">
                            @if(!empty($preview))
                                <div class="prose max-w-none">{!! $preview !!}</div>
                            @else
                                <div class="text-sm text-gray-500">Start typing your HTML above…</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

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


