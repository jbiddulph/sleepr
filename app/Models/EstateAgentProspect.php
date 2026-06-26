<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EstateAgentProspect extends Model
{
    protected $connection = 'pgsql_public';

    protected $table = 'sleepr_estate_agent_prospects';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'best_emails' => 'array',
            'other_business_emails' => 'array',
            'emails_found' => 'array',
            'last_contacted_at' => 'datetime',
        ];
    }

    public function notes(): HasMany
    {
        return $this->hasMany(EstateAgentProspectNote::class, 'prospect_id');
    }

    public function emailOptions(): array
    {
        $emails = array_merge(
            $this->best_emails ?? [],
            $this->other_business_emails ?? [],
            $this->emails_found ?? [],
        );

        return array_values(array_unique(array_filter($emails)));
    }

    public function outreachStatusLabel(): string
    {
        return str($this->outreach_status)->replace('_', ' ')->title();
    }
}
