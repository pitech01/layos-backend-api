<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class DirectMessage extends Model
{
    protected $fillable = [
        'sender_id',
        'receiver_id',
        'content',
        'attachment_url',
        'attachment_name',
        'is_read',
        'is_deleted',
        'storage_provider'
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    protected function attachmentUrl(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => \App\Services\MediaService::getMediaUrl($this, 'attachment_url', $value)
        );
    }
}
