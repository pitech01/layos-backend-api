<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    protected $table = 'students';

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'bio',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function cohorts()
    {
        return $this->belongsToMany(Cohort::class, 'cohort_student', 'student_id', 'cohort_id')
                    ->withPivot('status', 'progress')
                    ->withTimestamps();
    }

    public function submissions()
    {
        return $this->hasMany(AssignmentSubmission::class, 'student_id');
    }

    public function completedLessons()
    {
        return $this->belongsToMany(Lesson::class, 'lesson_student', 'student_id', 'lesson_id')
                    ->withTimestamps()
                    ->withPivot('completed', 'score', 'answers');
    }
}
