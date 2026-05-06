<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cohort extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'start_date',
        'end_date',
        'enrollment_deadline',
        'timezone',
        'visibility',
        'instructor_id',
        'course_id'
    ];
    protected $casts = [
        'instructor_id' => 'integer',
        'course_id' => 'integer',
    ];



    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function students()
    {
        return $this->belongsToMany(User::class, 'cohort_student', 'cohort_id', 'student_id')
                    ->withPivot('status', 'progress')
                    ->withTimestamps();
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }
}
