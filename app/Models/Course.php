<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class Course extends Model
{
    protected $fillable = [
        'title',
        'description',
        'category',
        'target_level',
        'language',
        'duration',
        'thumbnail',
        'instructor_id',
        'storage_provider'
    ];
 
    protected $casts = [
        'instructor_id' => 'integer',
    ];


    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function modules()
    {
        return $this->hasMany(Module::class)->orderBy('order');
    }

    public function cohorts()
    {
        return $this->hasMany(Cohort::class);
    }

    protected function thumbnail(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => \App\Services\MediaService::getMediaUrl($this, 'thumbnail', $value)
        );
    }
    public function recalculateAllStudentsProgress()
    {
        $this->load(['cohorts.students']);
        
        foreach ($this->cohorts as $cohort) {
            foreach ($cohort->students as $student) {
                $this->updateStudentProgress($student, $cohort);
            }
        }
    }

    public function updateStudentProgress(User $student, Cohort $cohort)
    {
        $courseId = $this->id;
        $totalLessonsQuery = Lesson::whereHas('module', function($q) use ($courseId) {
            $q->where('course_id', $courseId);
        });

        $totalLessonsWithDocs = (clone $totalLessonsQuery)->whereNotNull('file_url')->count();
        
        if ($totalLessonsWithDocs > 0) {
            $totalItems = $totalLessonsWithDocs;
            $completedCount = $student->completedLessons()
                ->whereHas('module', function($q) use ($courseId) {
                    $q->where('course_id', $courseId);
                })
                ->whereNotNull('lessons.file_url')
                ->count();
        } else {
            $totalItems = $totalLessonsQuery->count();
            $completedCount = $student->completedLessons()
                ->whereHas('module', function($q) use ($courseId) {
                    $q->where('course_id', $courseId);
                })
                ->count();
        }

        $progress = $totalItems > 0 ? round(($completedCount / $totalItems) * 100) : 0;
        
        $student->cohorts()->updateExistingPivot($cohort->id, ['progress' => $progress]);
        
        return $progress;
    }
}
