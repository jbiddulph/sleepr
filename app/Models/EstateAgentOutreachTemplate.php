<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstateAgentOutreachTemplate extends Model
{
    protected $connection = 'pgsql_public';

    protected $table = 'sleepr_estate_agent_outreach_templates';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function renderSubject(EstateAgentProspect $prospect, ?User $user = null): string
    {
        return \App\Support\ProspectTemplateRenderer::render($this->subject, $prospect, $user);
    }

    public function renderBody(EstateAgentProspect $prospect, ?User $user = null): string
    {
        return \App\Support\ProspectTemplateRenderer::render($this->body, $prospect, $user);
    }
}
