<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Casts\Attribute;

class AssignmentSubmission extends Model
{
    protected $fillable = ['assignment_id', 'student_id', 'answer_text', 'submission_file', 'submitted_at', 'storage_provider'];
    
    protected $appends = ['submission_file_url'];

    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    protected function submissionFileUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => \App\Services\MediaService::getMediaUrl($this, 'submission_file')
        );
    }
}
