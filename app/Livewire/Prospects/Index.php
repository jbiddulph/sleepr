<?php

namespace App\Livewire\Prospects;

use App\Models\EstateAgentOutreachTemplate;
use App\Models\EstateAgentProspect;
use App\Models\EstateAgentProspectGroup;
use App\Services\ScheduleProspectOutreachNotes;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'tailwind';

    public string $query = '';

    public string $statusFilter = 'all';

    /** @var array<int, string> */
    public array $selectedGroupIds = [];

    /** @var array<int, string> */
    public array $selectedProspectIds = [];

    public ?string $move_to_group_id = null;

    public ?string $template_id = null;

    public bool $onlyReadyForBulk = true;

    public ?string $bulk_send_date = null;

    public bool $bulk_stagger = true;

    public string $status = '';

    public bool $showTemplateForm = false;

    public bool $showGroupForm = false;

    public ?string $edit_group_id = null;

    public bool $showProspectForm = false;

    public ?string $edit_prospect_id = null;

    public ?string $edit_template_id = null;

    #[Validate('required|string|min:2|max:120')]
    public string $template_name = '';

    #[Validate('required|string|min:2|max:255')]
    public string $template_subject = '';

    #[Validate('required|string|min:10')]
    public string $template_body = '';

    #[Validate('required|string|min:2|max:120')]
    public string $group_name = '';

    #[Validate('required|string|min:2|max:255')]
    public string $prospect_agency_name = '';

    #[Validate('required|string|min:2|max:255')]
    public string $prospect_town = '';

    #[Validate('nullable|url|max:500')]
    public ?string $prospect_website = null;

    #[Validate('nullable|url|max:500')]
    public ?string $prospect_contact_page_url = null;

    #[Validate('nullable|uuid')]
    public ?string $prospect_group_id = null;

    #[Validate('required|in:pending,reviewing,ready,contacted,replied,not_interested,no_email')]
    public string $prospect_outreach_status = 'pending';

    #[Validate('nullable|email|max:255')]
    public ?string $prospect_selected_email = null;

    #[Validate('nullable|string|max:255')]
    public ?string $prospect_review_status = null;

    public function mount(): void
    {
        $this->template_id = session('prospects_template_id');
        $this->bulk_send_date = now('UTC')
            ->addDay()
            ->setTime(10, 0)
            ->format('Y-m-d\TH:i');

        $storedGroupIds = session('prospects_group_ids');
        if (is_array($storedGroupIds) && $storedGroupIds !== []) {
            $this->selectedGroupIds = $storedGroupIds;
        } else {
            $agentsGroupId = EstateAgentProspectGroup::query()
                ->where('name', 'Agents')
                ->value('id');

            $this->selectedGroupIds = $agentsGroupId ? [$agentsGroupId] : [];
            session(['prospects_group_ids' => $this->selectedGroupIds]);
        }
    }

    public function updatedTemplateId(): void
    {
        session(['prospects_template_id' => $this->template_id]);
    }

    public function updatedSelectedGroupIds(): void
    {
        session(['prospects_group_ids' => $this->selectedGroupIds]);
        $this->resetPage();
        $this->selectedProspectIds = [];
    }

    public function updatedQuery(): void
    {
        $this->resetPage();
        $this->selectedProspectIds = [];
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
        $this->selectedProspectIds = [];
    }

    public function selectAllGroups(): void
    {
        $this->selectedGroupIds = EstateAgentProspectGroup::query()
            ->orderBy('name')
            ->pluck('id')
            ->all();
        session(['prospects_group_ids' => $this->selectedGroupIds]);
        $this->resetPage();
        $this->selectedProspectIds = [];
    }

    public function clearGroupSelection(): void
    {
        $this->selectedGroupIds = [];
        session(['prospects_group_ids' => []]);
        $this->resetPage();
        $this->selectedProspectIds = [];
    }

    public function toggleProspect(string $prospectId): void
    {
        if (in_array($prospectId, $this->selectedProspectIds, true)) {
            $this->selectedProspectIds = array_values(array_filter(
                $this->selectedProspectIds,
                fn (string $id): bool => $id !== $prospectId
            ));
        } else {
            $this->selectedProspectIds[] = $prospectId;
        }
    }

    public function togglePageSelection(): void
    {
        $pageIds = $this->prospectsQuery()
            ->orderBy('town')
            ->orderBy('agency_name')
            ->forPage($this->getPage(), 25)
            ->pluck('id')
            ->all();

        $allSelected = $pageIds !== []
            && collect($pageIds)->every(fn (string $id): bool => in_array($id, $this->selectedProspectIds, true));

        if ($allSelected) {
            $this->selectedProspectIds = array_values(array_filter(
                $this->selectedProspectIds,
                fn (string $id): bool => ! in_array($id, $pageIds, true)
            ));
        } else {
            $this->selectedProspectIds = array_values(array_unique([
                ...$this->selectedProspectIds,
                ...$pageIds,
            ]));
        }
    }

    public function moveSelectedProspects(): void
    {
        if ($this->selectedProspectIds === [] || ! $this->move_to_group_id) {
            $this->status = __('Select prospects and a target group first.');
            return;
        }

        $group = EstateAgentProspectGroup::find($this->move_to_group_id);
        if (! $group) {
            $this->status = __('Target group not found.');
            return;
        }

        $moved = EstateAgentProspect::query()
            ->whereIn('id', $this->selectedProspectIds)
            ->update(['group_id' => $group->id]);

        $this->selectedProspectIds = [];
        $this->move_to_group_id = null;
        $this->status = __('Moved :count prospects to :group.', [
            'count' => $moved,
            'group' => $group->name,
        ]);
    }

    public function openGroupForm(?string $groupId = null): void
    {
        $this->showGroupForm = true;
        $this->edit_group_id = $groupId;

        if ($groupId) {
            $group = EstateAgentProspectGroup::findOrFail($groupId);
            $this->group_name = $group->name;
        } else {
            $this->group_name = '';
        }
    }

    public function saveGroup(): void
    {
        $this->validateOnly('group_name');

        if ($this->edit_group_id) {
            $group = EstateAgentProspectGroup::findOrFail($this->edit_group_id);
            $group->update(['name' => $this->group_name]);
            $this->status = __('Group :name updated.', ['name' => $group->name]);
        } else {
            $group = EstateAgentProspectGroup::create([
                'id' => (string) Str::uuid(),
                'name' => $this->group_name,
            ]);

            $this->selectedGroupIds = array_values(array_unique([
                ...$this->selectedGroupIds,
                $group->id,
            ]));
            session(['prospects_group_ids' => $this->selectedGroupIds]);
            $this->status = __('Group :name created.', ['name' => $group->name]);
        }

        $this->showGroupForm = false;
        $this->edit_group_id = null;
        $this->group_name = '';
    }

    public function deleteGroup(string $groupId): void
    {
        $group = EstateAgentProspectGroup::findOrFail($groupId);
        $name = $group->name;
        $group->delete();

        $this->selectedGroupIds = array_values(array_filter(
            $this->selectedGroupIds,
            fn (string $id): bool => $id !== $groupId
        ));
        session(['prospects_group_ids' => $this->selectedGroupIds]);

        if ($this->edit_group_id === $groupId) {
            $this->showGroupForm = false;
            $this->edit_group_id = null;
            $this->group_name = '';
        }

        $this->status = __('Group :name deleted. Its prospects are now ungrouped.', ['name' => $name]);
    }

    public function openProspectForm(?string $prospectId = null): void
    {
        $this->showProspectForm = true;
        $this->edit_prospect_id = $prospectId;

        if ($prospectId) {
            $prospect = EstateAgentProspect::findOrFail($prospectId);
            $this->prospect_agency_name = $prospect->agency_name;
            $this->prospect_town = $prospect->town;
            $this->prospect_website = $prospect->website;
            $this->prospect_contact_page_url = $prospect->contact_page_url;
            $this->prospect_group_id = $prospect->group_id;
            $this->prospect_outreach_status = $prospect->outreach_status;
            $this->prospect_selected_email = $prospect->selected_email;
            $this->prospect_review_status = $prospect->review_status;
        } else {
            $this->reset([
                'prospect_agency_name',
                'prospect_town',
                'prospect_website',
                'prospect_contact_page_url',
                'prospect_selected_email',
                'prospect_review_status',
            ]);
            $this->prospect_outreach_status = 'pending';
            $this->prospect_group_id = $this->selectedGroupIds[0] ?? EstateAgentProspectGroup::query()
                ->where('name', 'Agents')
                ->value('id');
        }
    }

    public function saveProspect(): void
    {
        $this->validate([
            'prospect_agency_name' => 'required|string|min:2|max:255',
            'prospect_town' => 'required|string|min:2|max:255',
            'prospect_website' => 'nullable|url|max:500',
            'prospect_contact_page_url' => 'nullable|url|max:500',
            'prospect_group_id' => 'nullable|uuid',
            'prospect_outreach_status' => 'required|in:pending,reviewing,ready,contacted,replied,not_interested,no_email',
            'prospect_selected_email' => 'nullable|email|max:255',
            'prospect_review_status' => 'nullable|string|max:255',
        ]);

        $duplicateQuery = EstateAgentProspect::query()
            ->where('agency_name', $this->prospect_agency_name)
            ->where('town', $this->prospect_town);

        if ($this->edit_prospect_id) {
            $duplicateQuery->where('id', '!=', $this->edit_prospect_id);
        }

        if ($duplicateQuery->exists()) {
            $this->addError('prospect_agency_name', __('A prospect with this agency and town already exists.'));
            return;
        }

        $data = [
            'agency_name' => $this->prospect_agency_name,
            'town' => $this->prospect_town,
            'website' => $this->prospect_website ?: null,
            'contact_page_url' => $this->prospect_contact_page_url ?: null,
            'group_id' => $this->prospect_group_id ?: null,
            'outreach_status' => $this->prospect_outreach_status,
            'selected_email' => $this->prospect_selected_email ?: null,
            'review_status' => $this->prospect_review_status ?: null,
        ];

        if ($this->edit_prospect_id) {
            $prospect = EstateAgentProspect::findOrFail($this->edit_prospect_id);
            $prospect->update($data);
            $this->status = __('Prospect updated.');
        } else {
            EstateAgentProspect::create([
                'id' => (string) Str::uuid(),
                ...$data,
                'best_emails' => [],
                'other_business_emails' => [],
                'emails_found' => $this->prospect_selected_email ? [$this->prospect_selected_email] : [],
            ]);
            $this->status = __('Prospect created.');
        }

        $this->showProspectForm = false;
        $this->edit_prospect_id = null;
        $this->reset([
            'prospect_agency_name',
            'prospect_town',
            'prospect_website',
            'prospect_contact_page_url',
            'prospect_group_id',
            'prospect_outreach_status',
            'prospect_selected_email',
            'prospect_review_status',
        ]);
        $this->prospect_outreach_status = 'pending';
    }

    public function deleteProspect(string $prospectId): void
    {
        $prospect = EstateAgentProspect::findOrFail($prospectId);
        $label = $prospect->agency_name.' — '.$prospect->town;
        $prospect->delete();

        $this->selectedProspectIds = array_values(array_filter(
            $this->selectedProspectIds,
            fn (string $id): bool => $id !== $prospectId
        ));

        if ($this->edit_prospect_id === $prospectId) {
            $this->showProspectForm = false;
            $this->edit_prospect_id = null;
        }

        $this->status = __('Deleted prospect :label.', ['label' => $label]);
    }

    public function deleteSelectedProspects(): void
    {
        if ($this->selectedProspectIds === []) {
            $this->status = __('Select prospects to delete first.');
            return;
        }

        $deleted = EstateAgentProspect::query()
            ->whereIn('id', $this->selectedProspectIds)
            ->delete();

        $this->selectedProspectIds = [];
        $this->status = __('Deleted :count prospect(s).', ['count' => $deleted]);
    }

    public function openTemplateForm(?string $templateId = null): void
    {
        $this->showTemplateForm = true;
        $this->edit_template_id = $templateId;

        if ($templateId) {
            $template = EstateAgentOutreachTemplate::findOrFail($templateId);
            $this->template_name = $template->name;
            $this->template_subject = $template->subject;
            $this->template_body = $template->body;
        } else {
            $this->reset(['template_name', 'template_subject', 'template_body']);
            $this->template_subject = 'Quick idea for {{agency_name}} in {{town}}';
            $this->template_body = "Hi there,\n\nI came across {{agency_name}} in {{town}} and wanted to reach out.\n\nBest regards,\n{{sender_name}}";
        }
    }

    public function saveTemplate(): void
    {
        $this->validate([
            'template_name' => 'required|string|min:2|max:120',
            'template_subject' => 'required|string|min:2|max:255',
            'template_body' => 'required|string|min:10',
        ]);

        if ($this->edit_template_id) {
            $template = EstateAgentOutreachTemplate::findOrFail($this->edit_template_id);
            $template->update([
                'name' => $this->template_name,
                'subject' => $this->template_subject,
                'body' => $this->template_body,
            ]);
            $this->status = __('Template updated.');
        } else {
            $template = EstateAgentOutreachTemplate::create([
                'id' => (string) Str::uuid(),
                'name' => $this->template_name,
                'subject' => $this->template_subject,
                'body' => $this->template_body,
                'is_active' => true,
            ]);
            $this->template_id = $template->id;
            session(['prospects_template_id' => $template->id]);
            $this->status = __('Template created.');
        }

        $this->showTemplateForm = false;
        $this->reset(['edit_template_id', 'template_name', 'template_subject', 'template_body']);
    }

    public function bulkCreateDrafts(ScheduleProspectOutreachNotes $scheduler): void
    {
        if (! $this->template_id) {
            $this->status = __('Choose a template first.');
            return;
        }

        if ($error = $scheduler->validateSendDate($this->bulk_send_date)) {
            $this->addError('bulk_send_date', $error);
            return;
        }

        $template = EstateAgentOutreachTemplate::query()
            ->where('is_active', true)
            ->find($this->template_id);

        if (! $template) {
            $this->status = __('Template not found.');
            return;
        }

        $prospects = $this->prospectsQuery()
            ->when($this->onlyReadyForBulk, fn ($q) => $q->where('outreach_status', 'ready'))
            ->where(function ($q) {
                $q->whereNotNull('selected_email')
                    ->orWhereRaw('coalesce(array_length(best_emails, 1), 0) > 0')
                    ->orWhereRaw('coalesce(array_length(emails_found, 1), 0) > 0');
            })
            ->get();

        $created = $scheduler->schedule(
            prospects: $prospects,
            template: $template,
            user: Auth::user(),
            startAt: Carbon::parse($this->bulk_send_date, 'UTC'),
            stagger: $this->bulk_stagger,
        );

        $window = $this->bulk_stagger
            ? __('Emails are spread in groups of 33 every 10 minutes.')
            : __('All emails are scheduled for the same time.');

        $this->status = __('Scheduled :count outreach emails. :window They will appear in Notes and send automatically.', [
            'count' => $created,
            'window' => $window,
        ]);
    }

    protected function prospectsQuery()
    {
        return EstateAgentProspect::query()
            ->with('group')
            ->when($this->selectedGroupIds !== [], fn ($q) => $q->whereIn('group_id', $this->selectedGroupIds))
            ->when($this->query !== '', function ($q) {
                $term = '%'.$this->query.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('agency_name', 'ilike', $term)
                        ->orWhere('town', 'ilike', $term)
                        ->orWhere('selected_email', 'ilike', $term);
                });
            })
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('outreach_status', $this->statusFilter));
    }

    public function getBulkTargetCountProperty(): int
    {
        if (! $this->template_id) {
            return 0;
        }

        return $this->prospectsQuery()
            ->when($this->onlyReadyForBulk, fn ($q) => $q->where('outreach_status', 'ready'))
            ->where(function ($q) {
                $q->whereNotNull('selected_email')
                    ->orWhereRaw('coalesce(array_length(best_emails, 1), 0) > 0')
                    ->orWhereRaw('coalesce(array_length(emails_found, 1), 0) > 0');
            })
            ->count();
    }

    public function getBulkSendWindowProperty(): ?string
    {
        if (! $this->bulk_send_date || $this->bulkTargetCount === 0) {
            return null;
        }

        try {
            $start = Carbon::parse($this->bulk_send_date, 'UTC');
        } catch (\Throwable) {
            return null;
        }

        if (! $this->bulk_stagger) {
            return $start->format('M j, Y g:i A').' UTC';
        }

        $slots = (int) ceil($this->bulkTargetCount / 33);
        $end = $start->copy()->addMinutes(max(0, $slots - 1) * 10);

        return $start->format('M j, Y g:i A').' – '.$end->format('g:i A').' UTC';
    }

    public function getPageProspectIdsProperty(): array
    {
        return $this->prospectsQuery()
            ->orderBy('town')
            ->orderBy('agency_name')
            ->forPage($this->getPage(), 25)
            ->pluck('id')
            ->all();
    }

    public function getAllPageProspectsSelectedProperty(): bool
    {
        $pageIds = $this->pageProspectIds;

        return $pageIds !== []
            && collect($pageIds)->every(fn (string $id): bool => in_array($id, $this->selectedProspectIds, true));
    }

    public function render()
    {
        $prospects = $this->prospectsQuery()
            ->orderBy('town')
            ->orderBy('agency_name')
            ->paginate(25);

        $countsQuery = EstateAgentProspect::query()
            ->when($this->selectedGroupIds !== [], fn ($q) => $q->whereIn('group_id', $this->selectedGroupIds));

        $counts = (clone $countsQuery)
            ->selectRaw('outreach_status, count(*) as total')
            ->groupBy('outreach_status')
            ->pluck('total', 'outreach_status');

        $groups = EstateAgentProspectGroup::query()
            ->withCount('prospects')
            ->orderBy('name')
            ->get();

        $templates = EstateAgentOutreachTemplate::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('livewire.prospects.index', [
            'prospects' => $prospects,
            'counts' => $counts,
            'groups' => $groups,
            'templates' => $templates,
        ]);
    }
}
