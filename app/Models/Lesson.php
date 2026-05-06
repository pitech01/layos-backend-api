<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class Lesson extends Model
{
    protected $fillable = [
        'module_id',
        'title',
        'type',
        'duration',
        'description',
        'is_locked',
        'is_preview',
        'video_url',
        'video_source',
        'live_date',
        'live_time',
        'live_platform',
        'live_link',
        'file_name',
        'file_url',
        'quiz_data',
        'order',
        'storage_provider'
    ];

    protected $casts = [
        'quiz_data' => 'array',
        'is_locked' => 'boolean',
        'is_preview' => 'boolean'
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function students()
    {
        return $this->belongsToMany(User::class, 'lesson_student', 'lesson_id', 'student_id')->withTimestamps()->withPivot('completed', 'score', 'answers');
    }

    protected function videoUrl(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => \App\Services\MediaService::getMediaUrl($this, 'video_url', $value)
        );
    }

    protected function fileUrl(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => \App\Services\MediaService::getMediaUrl($this, 'file_url', $value)
        );
    }
}
