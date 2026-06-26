<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EstateAgentProspectGroup extends Model
{
    protected $connection = 'pgsql_public';

    protected $table = 'sleepr_estate_agent_prospect_groups';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public function prospects(): HasMany
    {
        return $this->hasMany(EstateAgentProspect::class, 'group_id');
    }
}
