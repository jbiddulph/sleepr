<div class="space-y-6">
    <h1 class="text-2xl font-bold">Recipients</h1>

    <div class="flex items-center gap-3">
        <input type="text" wire:model.debounce.400ms="query" class="border rounded p-2 w-80" placeholder="Search email..." />
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full border divide-y divide-zinc-200 dark:divide-zinc-800">
            <thead class="bg-zinc-50 dark:bg-zinc-900">
                <tr>
                    <th class="p-2 text-left text-sm font-medium">Email</th>
                    <th class="p-2 text-left text-sm font-medium">Note</th>
                    <th class="p-2 text-left text-sm font-medium">Send at</th>
                    <th class="p-2 text-left text-sm font-medium">Sent</th>
                    <th class="p-2 text-left text-sm font-medium">Hearted</th>
                    <th class="p-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @foreach($rows as $row)
                    <tr>
                        <td class="p-2">
                            @if($editId === $row->id)
                                <input type="email" class="border rounded p-1 w-64" wire:model.defer="editEmail" />
                                @error('editEmail') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                            @else
                                {{ $row->email }}
                            @endif
                        </td>
                        <td class="p-2">{{ optional($row->note)->title ?? '—' }}</td>
                        <td class="p-2">
                            @if($editId === $row->id)
                                <input type="datetime-local" class="border rounded p-1" wire:model.defer="editSendDate" />
                                @error('editSendDate') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                            @else
                                {{ optional($row->send_date)->toDateTimeString() ?? '—' }}
                            @endif
                        </td>
                        <td class="p-2">{{ optional($row->sent_at)->diffForHumans() ?? '—' }}</td>
                        <td class="p-2">{{ optional($row->hearted_at)->diffForHumans() ?? '—' }}</td>
                        <td class="p-2 text-right space-x-2">
                            @if($editId === $row->id)
                                <button wire:click="save" class="px-3 py-1 bg-green-600 text-white rounded">Save</button>
                                <button wire:click="$set('editId', null)" class="px-3 py-1 border rounded">Cancel</button>
                            @else
                                <button wire:click="edit({{ $row->id }})" class="px-3 py-1 border rounded">Edit</button>
                                @if(is_null($row->sent_at))
                                    <button wire:click="cancel({{ $row->id }})" class="px-3 py-1 text-red-600">Cancel send</button>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>


