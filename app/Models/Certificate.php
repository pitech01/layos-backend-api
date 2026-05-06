<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Casts\Attribute;

class Certificate extends Model
{
    protected $fillable = [
        'certificate_uuid', 'user_id', 'course_id', 'full_name',
        'course_title', 'qr_code_path', 'certificate_path',
        'issued_by', 'issued_at', 'storage_provider'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    protected function certificatePath(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => \App\Services\MediaService::getMediaUrl($this, 'certificate_path', $value)
        );
    }

    protected function qrCodePath(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => \App\Services\MediaService::getMediaUrl($this, 'qr_code_path', $value)
        );
    }
}
