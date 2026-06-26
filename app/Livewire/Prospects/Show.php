<?php

namespace App\Livewire\Prospects;

use App\Models\EstateAgentOutreachTemplate;
use App\Models\EstateAgentProspect;
use App\Models\EstateAgentProspectGroup;
use App\Models\EstateAgentProspectNote;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Show extends Component
{
    public EstateAgentProspect $prospect;

    public string $status = '';

    public string $outreach_status = '';

    public ?string $selected_email = null;

    public ?string $template_id = null;

    public ?string $group_id = null;

    #[Validate('required|string|min:2|max:255')]
    public string $agency_name = '';

    #[Validate('required|string|min:2|max:255')]
    public string $town = '';

    #[Validate('nullable|url|max:500')]
    public ?string $website = null;

    #[Validate('nullable|url|max:500')]
    public ?string $contact_page_url = null;

    #[Validate('nullable|string|max:255')]
    public ?string $review_status = null;

    #[Validate('required|in:note,email_draft,email_sent,call')]
    public string $note_type = 'note';

    #[Validate('nullable|string|max:255')]
    public ?string $subject = null;

    #[Validate('required|string|min:2')]
    public string $body = '';

    #[Validate('nullable|email')]
    public ?string $email_to = null;

    #[Validate('nullable|email')]
    public ?string $email_from = null;

    public function mount(EstateAgentProspect $prospect): void
    {
        $this->prospect = $prospect;
        $this->agency_name = $prospect->agency_name;
        $this->town = $prospect->town;
        $this->website = $prospect->website;
        $this->contact_page_url = $prospect->contact_page_url;
        $this->review_status = $prospect->review_status;
        $this->outreach_status = (string) $prospect->outreach_status;
        $this->selected_email = $prospect->selected_email;
        $this->group_id = $prospect->group_id;
        $this->email_to = $prospect->selected_email;
        $this->email_from = Auth::user()?->email;
        $this->template_id = session('prospects_template_id');

        if ($this->template_id) {
            $this->applySelectedTemplate(false);
        }
    }

    public function updatedTemplateId(): void
    {
        session(['prospects_template_id' => $this->template_id]);
        $this->applySelectedTemplate();
    }

    public function applySelectedTemplate(bool $withStatus = true): void
    {
        if (! $this->template_id) {
            return;
        }

        $template = EstateAgentOutreachTemplate::query()
            ->where('is_active', true)
            ->find($this->template_id);

        if (! $template) {
            return;
        }

        $this->subject = $template->renderSubject($this->prospect, Auth::user());
        $this->body = $template->renderBody($this->prospect, Auth::user());
        $this->note_type = 'email_draft';
        $this->email_to = $this->selected_email
            ?: ($this->prospect->best_emails[0] ?? null)
            ?: ($this->prospect->emails_found[0] ?? null);

        if ($withStatus) {
            $this->status = __('Template applied for this prospect.');
        }
    }

    public function selectEmail(string $email): void
    {
        $this->selected_email = $email;
        $this->email_to = $email;
    }

    public function saveProspect(): void
    {
        $this->validate([
            'agency_name' => 'required|string|min:2|max:255',
            'town' => 'required|string|min:2|max:255',
            'website' => 'nullable|url|max:500',
            'contact_page_url' => 'nullable|url|max:500',
            'outreach_status' => 'required|in:pending,reviewing,ready,contacted,replied,not_interested,no_email',
            'selected_email' => 'nullable|email',
            'group_id' => 'nullable|uuid',
            'review_status' => 'nullable|string|max:255',
        ]);

        $duplicateExists = EstateAgentProspect::query()
            ->where('agency_name', $this->agency_name)
            ->where('town', $this->town)
            ->where('id', '!=', $this->prospect->id)
            ->exists();

        if ($duplicateExists) {
            $this->addError('agency_name', __('A prospect with this agency and town already exists.'));
            return;
        }

        $this->prospect->update([
            'agency_name' => $this->agency_name,
            'town' => $this->town,
            'website' => $this->website ?: null,
            'contact_page_url' => $this->contact_page_url ?: null,
            'review_status' => $this->review_status ?: null,
            'outreach_status' => $this->outreach_status,
            'selected_email' => $this->selected_email ?: null,
            'group_id' => $this->group_id ?: null,
            'last_contacted_at' => $this->outreach_status === 'contacted'
                ? now()
                : $this->prospect->last_contacted_at,
        ]);

        $this->prospect->refresh();
        $this->status = __('Prospect updated.');
    }

    public function deleteProspect(): void
    {
        $this->prospect->delete();

        $this->redirectRoute('prospects.index', navigate: true);
    }

    public function addNote(): void
    {
        $this->validate();

        EstateAgentProspectNote::create([
            'id' => (string) Str::uuid(),
            'prospect_id' => $this->prospect->id,
            'created_by' => Auth::id(),
            'note_type' => $this->note_type,
            'subject' => $this->subject,
            'body' => $this->body,
            'email_to' => $this->email_to,
            'email_from' => $this->email_from,
            'sent_at' => $this->note_type === 'email_sent' ? now() : null,
        ]);

        if ($this->note_type === 'email_sent') {
            $this->prospect->update([
                'outreach_status' => 'contacted',
                'last_contacted_at' => now(),
                'selected_email' => $this->email_to ?: $this->prospect->selected_email,
            ]);
            $this->outreach_status = 'contacted';
            $this->selected_email = $this->prospect->selected_email;
        }

        $this->reset(['subject', 'body']);
        $this->note_type = 'note';
        $this->email_to = $this->selected_email;
        $this->status = __('Activity saved.');
        $this->prospect->refresh();

        if ($this->template_id) {
            $this->applySelectedTemplate(false);
        }
    }

    public function render()
    {
        $notes = $this->prospect->notes()
            ->with('author')
            ->latest()
            ->get();

        $templates = EstateAgentOutreachTemplate::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('livewire.prospects.show', [
            'notes' => $notes,
            'emailOptions' => $this->prospect->emailOptions(),
            'templates' => $templates,
            'groups' => EstateAgentProspectGroup::query()->orderBy('name')->get(),
        ]);
    }
}
