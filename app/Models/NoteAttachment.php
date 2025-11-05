<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteAttachment extends Model
{
    use HasFactory, HasUuid;

    protected $guarded = ['id'];

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }
}


