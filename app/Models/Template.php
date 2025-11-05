<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Template extends Model
{
    use HasFactory, HasUuid;

    protected $guarded = ['id'];

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }
}


