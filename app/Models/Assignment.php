<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Casts\Attribute;

class Assignment extends Model
{
    protected $fillable = ['title', 'description', 'cohort_id', 'created_by', 'due_date', 'assignment_file', 'storage_provider'];
    
    protected $appends = ['assignment_file_url'];

    public function cohort()
    {
        return $this->belongsTo(Cohort::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submissions()
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    protected function assignmentFileUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => \App\Services\MediaService::getMediaUrl($this, 'assignment_file')
        );
    }
}
