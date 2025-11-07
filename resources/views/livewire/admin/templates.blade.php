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
    <div x-show="showModal && !@js($edit_id)" x-cloak class="fixed inset-0 z-50 overflow-y-auto" x-transition>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" @click="showModal = false"></div>
            <!-- Modal Content -->
            <div class="relative inline-block align-bottom bg-white dark:bg-zinc-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-6xl sm:w-full">
                <div class="bg-white dark:bg-zinc-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4 max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Create Template</h3>
                        <button @click="showModal = false" wire:click="closeCreateModal" class="text-gray-400 dark:text-gray-300 hover:text-gray-600 dark:hover:text-gray-200">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <form wire:submit.prevent="save" class="space-y-4">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-900 dark:text-white">Name</label>
                <input type="text" wire:model="name" class="mt-1 w-full border rounded p-2 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white" />
                @error('name') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-900 dark:text-white">Slug</label>
                <input type="text" wire:model="slug" class="mt-1 w-full border rounded p-2 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white" placeholder="auto-generated from name if blank" />
                @error('slug') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-900 dark:text-white">HTML (use &#123;&#123;title&#125;&#125;, &#123;&#123;body&#125;&#125;, &#123;&#123;heart_url&#125;&#125;)</label>
            <div x-data="codeEditor(@entangle('html').defer)">
                <textarea
                    rows="18"
                    x-ref="textarea"
                    x-on:keydown.tab.prevent="insertTab($event)"
                    x-on:keydown.enter="autoIndent($event)"
                    x-on:input="syncToModel($event.target.value)"
                    x-on:blur="syncToModel($event.target.value, true)"
                    x-init="initialize($refs.textarea)"
                    spellcheck="false"
                    class="code-editor mt-1 w-full border rounded font-mono text-sm leading-6 bg-white/95 dark:bg-zinc-900 text-gray-900 dark:text-gray-100 border-zinc-300 dark:border-zinc-700 shadow-inner focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-zinc-800"
                >{{ $html }}</textarea>
            </div>
            @error('html') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="flex items-center gap-2">
            <label class="text-sm text-gray-900 dark:text-white">Active</label>
            <input type="checkbox" wire:model="is_active" class="h-4 w-4" />
        </div>
        <div class="flex items-center gap-2 mt-4">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Create Template</button>
            <button type="button" @click="showModal = false" wire:click="closeCreateModal" class="px-4 py-2 border rounded bg-white dark:bg-zinc-700 text-gray-900 dark:text-white hover:bg-gray-50 dark:hover:bg-zinc-600">Cancel</button>
        </div>
                    </form>

                    <div class="mt-6">
                        <h3 class="text-sm font-medium mb-2 text-gray-900 dark:text-white">Live preview</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div class="text-xs text-gray-600 dark:text-gray-300 mb-1">Mobile</div>
                                <div class="border rounded p-2 max-w-xs overflow-hidden bg-white dark:bg-zinc-700">
                                    @if(!empty($preview))
                                        <div class="prose prose-sm max-w-none dark:prose-invert">{!! $preview !!}</div>
                                    @else
                                        <div class="text-sm text-gray-500 dark:text-gray-400">Start typing your HTML above…</div>
                                    @endif
                                </div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-600 dark:text-gray-300 mb-1">Desktop</div>
                                <div class="border rounded p-4 overflow-hidden bg-white dark:bg-zinc-700">
                                    @if(!empty($preview))
                                        <div class="prose max-w-none dark:prose-invert">{!! $preview !!}</div>
                                    @else
                                        <div class="text-sm text-gray-500 dark:text-gray-400">Start typing your HTML above…</div>
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
        <div class="border rounded p-4 bg-gray-50 dark:bg-zinc-800">
            <h3 class="text-lg font-medium mb-4 text-gray-900 dark:text-white">Edit Template</h3>
            <form wire:submit.prevent="save" class="space-y-4 text-gray-900 dark:text-white">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">Name</label>
                        <input type="text" wire:model="name" class="mt-1 w-full border rounded p-2 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white" />
                        @error('name') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">Slug</label>
                        <input type="text" wire:model="slug" class="mt-1 w-full border rounded p-2 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white" placeholder="auto-generated from name if blank" />
                        @error('slug') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 dark:text-white">HTML (use &#123;&#123;title&#125;&#125;, &#123;&#123;body&#125;&#125;, &#123;&#123;heart_url&#125;&#125;)</label>
                    <div x-data="codeEditor(@entangle('html').defer)">
                        <textarea
                            rows="18"
                            x-ref="textarea"
                            x-on:keydown.tab.prevent="insertTab($event)"
                            x-on:keydown.enter="autoIndent($event)"
                            x-on:input="syncToModel($event.target.value)"
                            x-on:blur="syncToModel($event.target.value, true)"
                            x-init="initialize($refs.textarea)"
                            spellcheck="false"
                            class="code-editor mt-1 w-full border rounded font-mono text-sm leading-6 bg-white/95 dark:bg-zinc-900 text-gray-900 dark:text-gray-100 border-zinc-300 dark:border-zinc-700 shadow-inner focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-zinc-800"
                        >{{ $html }}</textarea>
                    </div>
                    @error('html') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-sm text-gray-900 dark:text-white">Active</label>
                    <input type="checkbox" wire:model="is_active" class="h-4 w-4 border-gray-300 dark:border-zinc-600 text-blue-600 focus:ring-blue-500" />
                </div>
                <div class="flex items-center gap-2 mt-4">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Update Template</button>
                    <button type="button" wire:click="cancel" class="px-4 py-2 border rounded bg-white dark:bg-zinc-700 text-gray-900 dark:text-white hover:bg-gray-50 dark:hover:bg-zinc-600">Cancel</button>
                </div>
            </form>
            <div class="mt-6">
                <h3 class="text-sm font-medium mb-2 text-gray-900 dark:text-white">Live preview</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <div class="text-xs text-gray-600 dark:text-gray-300 mb-1">Mobile</div>
                        <div class="border rounded p-2 max-w-xs overflow-hidden bg-white dark:bg-zinc-900 border-zinc-200 dark:border-zinc-700">
                            @if(!empty($preview))
                                <div class="prose prose-sm max-w-none dark:prose-invert">{!! $preview !!}</div>
                            @else
                                <div class="text-sm text-gray-500 dark:text-gray-400">Start typing your HTML above…</div>
                            @endif
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-600 dark:text-gray-300 mb-1">Desktop</div>
                        <div class="border rounded p-4 overflow-hidden bg-white dark:bg-zinc-900 border-zinc-200 dark:border-zinc-700">
                            @if(!empty($preview))
                                <div class="prose max-w-none dark:prose-invert">{!! $preview !!}</div>
                            @else
                                <div class="text-sm text-gray-500 dark:text-gray-400">Start typing your HTML above…</div>
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


