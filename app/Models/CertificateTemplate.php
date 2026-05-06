<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Casts\Attribute;

class CertificateTemplate extends Model
{
    protected $fillable = [
        'course_id', 'template_path', 'name_x', 'name_y',
        'course_x', 'course_y', 'date_x', 'date_y',
        'cert_id_x', 'cert_id_y', 'qr_x', 'qr_y', 'qr_size',
        'font_color', 'font_size',
        'bg_x', 'bg_y', 'bg_width', 'bg_height', 'bg_object_fit',
        'layout_json', 'storage_provider'
    ];
    
    protected $casts = [
        'layout_json' => 'array'
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    protected function templatePath(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => \App\Services\MediaService::getMediaUrl($this, 'template_path', $value)
        );
    }
}
