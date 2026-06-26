<?php

namespace App\Livewire\Prospects;

use App\Models\EstateAgentOutreachTemplate;
use App\Models\EstateAgentProspect;
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

    public ?string $template_id = null;

    public bool $onlyReadyForBulk = true;

    public ?string $bulk_send_date = null;

    public bool $bulk_stagger = true;

    public string $status = '';

    public bool $showTemplateForm = false;

    public ?string $edit_template_id = null;

    #[Validate('required|string|min:2|max:120')]
    public string $template_name = '';

    #[Validate('required|string|min:2|max:255')]
    public string $template_subject = '';

    #[Validate('required|string|min:10')]
    public string $template_body = '';

    public function mount(): void
    {
        $this->template_id = session('prospects_template_id');
        $this->bulk_send_date = now('UTC')
            ->addDay()
            ->setTime(10, 0)
            ->format('Y-m-d\TH:i');
    }

    public function updatedTemplateId(): void
    {
        session(['prospects_template_id' => $this->template_id]);
    }

    public function updatedQuery(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
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
        $this->validate();

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

    public function render()
    {
        $prospects = $this->prospectsQuery()
            ->orderBy('town')
            ->orderBy('agency_name')
            ->paginate(25);

        $counts = EstateAgentProspect::query()
            ->selectRaw('outreach_status, count(*) as total')
            ->groupBy('outreach_status')
            ->pluck('total', 'outreach_status');

        $templates = EstateAgentOutreachTemplate::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('livewire.prospects.index', [
            'prospects' => $prospects,
            'counts' => $counts,
            'templates' => $templates,
        ]);
    }
}
