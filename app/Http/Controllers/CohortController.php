<?php

namespace App\Http\Controllers;

use App\Models\Cohort;
use Illuminate\Http\Request;

class CohortController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Cohort::with(['course', 'instructor', 'students']);
        
        if ($user && $user->role === 'instructor') {
            $query->where('instructor_id', $user->id);
        }
        
        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|string|unique:cohorts,id',
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'enrollment_deadline' => 'required|date',
            'timezone' => 'nullable|string',
            'visibility' => 'nullable|string',
            'instructor_id' => 'required|exists:students,id',
            'course_id' => 'nullable|exists:courses,id',
        ]);

        $cohort = Cohort::create($validated);

        return response()->json($cohort, 201);
    }

    public function show(Cohort $cohort)
    {
        return response()->json($cohort->load(['course.modules.lessons', 'instructor', 'students']));
    }

    public function update(Request $request, Cohort $cohort)
    {
        $validated = $request->validate([
            'course_id' => 'nullable|exists:courses,id',
            'name' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'enrollment_deadline' => 'nullable|date',
            'timezone' => 'nullable|string',
            'visibility' => 'nullable|string',
        ]);

        $cohort->update($validated);
        return response()->json($cohort);
    }

    public function destroy(Cohort $cohort)
    {
        $cohort->delete();
        return response()->json(null, 204);
    }

    public function updateStudentPivot(Request $request, Cohort $cohort, $userId)
    {
        $validated = $request->validate([
            'progress' => 'nullable|numeric|between:0,100',
            'status' => 'nullable|string|in:active,completed,dropped,inactive',
            'message' => 'nullable|string|max:1000',
        ]);

        $updateData = array_filter([
            'status' => $validated['status'] ?? null,
            'progress' => $validated['progress'] ?? null,
        ], fn($v) => !is_null($v));

        $cohort->students()->updateExistingPivot($userId, $updateData);

        // Notify Student
        try {
            $student = \App\Models\User::find($userId);
            if ($student) {
                \Illuminate\Support\Facades\Mail::to($student->email)->send(
                    new \App\Mail\StudentAccessNotification(
                        $student, 
                        $cohort->name, 
                        $validated['status'] ?? 'active', 
                        $validated['message'] ?? null
                    )
                );
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Access Mail Error: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Student record updated successfully',
            'student' => $cohort->students()->where('student_id', $userId)->first()
        ]);
    }

    public function dashboardStats(Request $request)
    {
        $user = $request->user();
        
        $cohortQuery = Cohort::where('instructor_id', $user->id);
        $cohortIds = $cohortQuery->pluck('id');
        
        $activeCohortsCount = $cohortQuery->count();
        
        $totalStudents = \App\Models\User::whereHas('cohorts', function($query) use ($cohortIds) {
            $query->whereIn('cohort_id', $cohortIds);
        })->count();
            
        $recentActivities = \Illuminate\Support\Facades\DB::table('cohort_student')
            ->join('students', 'cohort_student.student_id', '=', 'students.id')
            ->join('cohorts', 'cohort_student.cohort_id', '=', 'cohorts.id')
            ->whereIn('cohort_student.cohort_id', $cohortIds)
            ->select('students.name as user_name', 'cohorts.name as cohort_name', 'cohort_student.created_at as time')
            ->orderBy('cohort_student.created_at', 'desc')
            ->limit(5)
            ->get();

        $avgProgress = \Illuminate\Support\Facades\DB::table('cohort_student')
            ->whereIn('cohort_id', $cohortIds)
            ->whereNotNull('progress')
            ->avg('progress');
            
        return response()->json([
            'stats' => [
                'active_cohorts' => $activeCohortsCount,
                'total_students' => $totalStudents,
                'completion_rate' => $avgProgress ? round((float) $avgProgress) : 0, 
            ],
            'recent_activities' => $recentActivities
        ]);
    }

}
