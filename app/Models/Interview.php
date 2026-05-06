<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Casts\Attribute;

class Interview extends Model
{
    protected $fillable = [
        'title',
        'description',
        'document_path',
        'video_path',
        'cohort_id',
        'created_by',
        'storage_provider'
    ];

    protected $appends = ['document_url', 'video_url'];

    public function cohort()
    {
        return $this->belongsTo(Cohort::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function documentUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => \App\Services\MediaService::getMediaUrl($this, 'document_path')
        );
    }

    protected function videoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => \App\Services\MediaService::getMediaUrl($this, 'video_path')
        );
    }
}
