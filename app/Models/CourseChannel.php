<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'name',
        'description',
        'type',
        'created_by'
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages()
    {
        return $this->hasMany(ChannelMessage::class, 'channel_id');
    }
}
