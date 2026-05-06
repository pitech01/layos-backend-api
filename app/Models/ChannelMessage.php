<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class ChannelMessage extends Model
{
    protected $fillable = [
        'channel_id',
        'course_id',
        'user_id',
        'content',
        'type',
        'attachment_url',
        'attachment_name',
        'due_date',
        'is_deleted',
        'storage_provider'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    protected function attachmentUrl(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => \App\Services\MediaService::getMediaUrl($this, 'attachment_url', $value)
        );
    }
}
