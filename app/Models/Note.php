<?php

namespace App\Models;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Note extends Model
{
    use HasFactory, HasUuid;
    protected $guarded = ['id'];

    protected $casts = [
        'template' => 'string',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(NoteAttachment::class, 'note_id');
    }

    public function publishedNotes(User $user)
    {
        return $this->where('user_id', $user->id)->where('is_published', true)->get();
    }

    public function attachments()
    {
        return $this->hasMany(NoteAttachment::class, 'note_id');
    }
}
