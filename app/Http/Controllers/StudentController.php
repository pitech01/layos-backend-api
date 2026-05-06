<?php
 
 namespace App\Http\Controllers;
 
 use App\Models\User;
 use App\Models\Cohort;
 use App\Models\Lesson;
 use Illuminate\Http\Request;
 use Illuminate\Support\Facades\Hash;
 use Illuminate\Support\Facades\DB;
 
 class StudentController extends Controller
 {
     public function index(Request $request)
     {
         $user = $request->user();
         
         $query = User::where('role', 'student')->with('cohorts');
         
         if ($user && $user->role === 'instructor') {
             $cohortIds = Cohort::where('instructor_id', $user->id)->pluck('id');
             $query->whereHas('cohorts', function($q) use ($cohortIds) {
                 $q->whereIn('cohort_id', $cohortIds);
             });
         }
         
         $students = $query->latest()->get();
         return response()->json($students);
     }
 
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:students,email',
            'password' => 'nullable|string|min:8',
            'cohorts' => 'nullable|array',
            'cohorts.*' => 'exists:cohorts,id',
        ]);

        try {
            return DB::transaction(function () use ($validated) {
                $student = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password'] ?? 'password123'),
                    'role' => 'student',
                ]);

                if (!empty($validated['cohorts'])) {
                    $pivotData = [];
                    foreach ($validated['cohorts'] as $cohortId) {
                        $pivotData[$cohortId] = [
                            'status' => 'active',
                            'progress' => 0,
                        ];
                        
                        $cohort = \App\Models\Cohort::find($cohortId);
                        if ($cohort) {
                            \App\Models\ActivityLog::create([
                                'user_id' => $student->id,
                                'instructor_id' => $cohort->instructor_id,
                                'action' => 'enrolled',
                                'description' => $student->name . ' was enrolled in cohort: ' . $cohort->name,
                                'metadata' => ['cohort_id' => $cohort->id, 'course_id' => $cohort->course_id]
                            ]);
                        }
                    }
                    $student->cohorts()->attach($pivotData);
                }

                // Notify Student
                try {
                    $courseName = 'Layos Group Course';
                    if (!empty($validated['cohorts'])) {
                        $firstCohort = Cohort::find($validated['cohorts'][0]);
                        if ($firstCohort && $firstCohort->course) {
                            $courseName = $firstCohort->course->title;
                        }
                    }
                    $rawPassword = $validated['password'] ?? 'password123';
                    \Illuminate\Support\Facades\Mail::to($student->email)->send(new \App\Mail\CourseRegistrationNotification($student, $rawPassword, $courseName));
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Mail Error: ' . $e->getMessage());
                }

                return response()->json($student->load('cohorts'), 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create student.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, User $student)
    {
        if ($student->role !== 'student') {
            return response()->json(['message' => 'Not a student record.'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:students,email,' . $student->id,
            'password' => 'nullable|string|min:8',
            'cohorts' => 'nullable|array',
            'cohorts.*' => 'exists:cohorts,id',
        ]);

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if (!empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $student->update($data);

        if (isset($validated['cohorts'])) {
            $existingCohorts = $student->cohorts()->pluck('progress', 'cohorts.id')->toArray();
            $syncData = [];
            foreach ($validated['cohorts'] as $cohortId) {
                $syncData[$cohortId] = [
                    'status' => 'active',
                    'progress' => $existingCohorts[$cohortId] ?? 0,
                ];
            }
            $student->cohorts()->sync($syncData);
        }

        return response()->json($student->load('cohorts'));
    }

    public function assignCohorts(Request $request, User $student)
    {
        if ($student->role !== 'student') {
            return response()->json(['message' => 'Not a student record.'], 404);
        }

        $validated = $request->validate([
            'cohorts' => 'required|array',
            'cohorts.*' => 'exists:cohorts,id',
        ]);

        $pivotData = [];
        foreach ($validated['cohorts'] as $cohortId) {
            $pivotData[$cohortId] = [
                'status' => 'active',
                'progress' => 0,
            ];
        }

        $student->cohorts()->syncWithoutDetaching($pivotData);

        return response()->json([
            'message' => 'Cohorts assigned successfully',
            'student' => $student->load('cohorts')
        ]);
    }
 
     public function myEnrollments(Request $request)
     {
         $user = $request->user();
         if ($user->role !== 'student') {
             return response()->json(['message' => 'Unauthorized Access'], 403);
         }
         return response()->json($user->load(['cohorts' => function($q) {
            $q->wherePivotNotIn('status', ['dropped', 'inactive']);
        }, 'cohorts.course.modules.lessons', 'cohorts.instructor', 'completedLessons']));
    }

    public function myCourses(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'student') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        // Return courses array as expected by frontend
         $cohorts = $user->cohorts()
            ->wherePivotNotIn('status', ['dropped', 'inactive'])
            ->with(['course.modules.lessons', 'instructor'])
            ->get();
            
        $courses = $cohorts->map(function($cohort) {
            $course = $cohort->course;
            if ($course) {
                $course->instructor_name = $cohort->instructor->name ?? 'Instructor';
            }
            return $course;
        })->filter()->values();

        return response()->json($courses);
    }

     public function completeLesson(Request $request, Lesson $lesson)
     {
         $user = $request->user();
         
         // Permit instructors to authorize progress overrides
         if ($user->role !== 'student' && $user->role !== 'instructor' && $user->role !== 'admin') {
             return response()->json(['message' => 'Unauthorized'], 403);
         }

         if (in_array($user->role, ['instructor', 'admin'])) {
             $targetUserId = $request->input('student_id');
             if (!$targetUserId) {
                 return response()->json(['message' => 'Target student ID is required for override'], 400);
             }
             $user = \App\Models\User::where('role', 'student')->find($targetUserId);
             if (!$user) {
                 return response()->json(['message' => 'Student not found'], 404);
             }
         }

         // Ensure the lesson is associated with a cohort the user is enrolled in
         $cohortIds = $user->cohorts()->pluck('cohorts.id')->toArray();
         $courseId = $lesson->module->course_id;

         if (!$courseId) {
             $lesson->load('module.course');
             $courseId = $lesson->module->course_id;
         }
         
        $enrolled = $user->cohorts()
            ->wherePivot('status', '!=', 'dropped')
            ->where('course_id', $courseId)
            ->whereIn('cohorts.id', $cohortIds)
            ->first();
         
         if (!$enrolled) {
             return response()->json(['message' => 'Access Denied: Not enrolled or access revoked'], 403);
         }

         $isCompleted = $request->input('completed', true);
         $score = $request->input('score');
         $answers = $request->input('answers');

         if ($isCompleted) {
             $pivotData = ['completed' => true];
             if ($score !== null) $pivotData['score'] = $score;
             if ($answers !== null) $pivotData['answers'] = is_array($answers) ? json_encode($answers) : $answers;
             
             // Check if already completed to avoid duplicate logs
             $alreadyCompleted = $user->completedLessons()->where('lesson_id', $lesson->id)->exists();
             $user->completedLessons()->syncWithoutDetaching([$lesson->id => $pivotData]);
             
             if (!$alreadyCompleted) {
                 \App\Models\ActivityLog::create([
                     'user_id' => $user->id,
                     'instructor_id' => $enrolled->instructor_id,
                     'action' => 'lesson_completed',
                     'description' => $user->name . ' completed lesson: ' . $lesson->title,
                     'metadata' => ['lesson_id' => $lesson->id, 'course_id' => $courseId, 'score' => $score]
                 ]);
             }
         } else {
             $user->completedLessons()->detach($lesson->id);
         }

         // Recalculate progress for this student in this cohort using the centralized logic
         $course = \App\Models\Course::find($courseId);
         $progress = $course->updateStudentProgress($user, $enrolled);

         return response()->json([
             'success' => true,
             'completed' => $isCompleted,
             'progress' => $progress
         ]);
     }


     public function show(User $student)
     {
         if ($student->role !== 'student') {
             return response()->json(['message' => 'Not a student record.'], 404);
         }
          return response()->json($student->load(['cohorts' => function($q) {
              $q->wherePivot('status', '!=', 'dropped');
          }, 'cohorts.course.modules.lessons', 'completedLessons']));
      }
 

    public function destroy(User $student)
     {
         if ($student->role !== 'student') {
             return response()->json(['message' => 'Unauthorized deletion request.'], 403);
         }
         $student->delete();
         return response()->json(null, 204);
     }
 }
