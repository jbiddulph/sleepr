<?php

namespace App\Models;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    use HasFactory, HasUuid;
    protected $fillable = ['title', 'body', 'send_date', 'heart_count', 'user_id', 'is_published'];
}
