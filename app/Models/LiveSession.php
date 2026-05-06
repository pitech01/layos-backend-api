<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class LiveSession extends Model
{
    protected $fillable = [
        'title',
        'course_id',
        'scheduled_date',
        'start_time',
        'end_time',
        'meeting_link',
        'recording_link',
        'instructor_name'
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    protected function recordingLink(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                if ($value && str_contains($value, 'amazonaws.com')) {
                    try {
                        $path = ltrim(parse_url($value, PHP_URL_PATH), '/');
                        return Storage::disk('s3')->temporaryUrl($path, now()->addHours(12));
                    } catch (\Throwable $e) {
                        return $value;
                    }
                }
                return $value;
            }
        );
    }
}
