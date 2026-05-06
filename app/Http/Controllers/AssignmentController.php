<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class AssignmentController extends Controller
{
    public function index()
    {
        $assignments = Assignment::with(['cohort'])
            ->withCount('submissions')
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($assignments);
    }

    // Instructor: Create assignment
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'cohort_id' => 'required|exists:cohorts,id',
            'due_date' => 'nullable|date',
            'assignment_file' => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip|max:51200' // 50MB max
        ]);

        if ($request->hasFile('assignment_file')) {
            $bunnyService = new \App\Services\BunnyStorageService();
            $path = $bunnyService->upload($request->file('assignment_file'), 'assignments');
            $validated['assignment_file'] = $path;
            $validated['storage_provider'] = 'bunny';
        }

        $validated['created_by'] = Auth::id();

        $assignment = Assignment::create($validated);
        
        return response()->json($assignment, 201);
    }

    public function submissions($id)
    {
        $assignment = Assignment::findOrFail($id);
        $submissions = $assignment->submissions()->with('student')->get();

        return response()->json([
            'assignment' => $assignment,
            'submissions' => $submissions
        ]);
    }

    // Student: View assignments for their cohorts
    public function studentAssignments()
    {
        $user = Auth::user();
        $cohortIds = $user->cohorts()->pluck('cohorts.id');

        $assignments = Assignment::whereIn('cohort_id', $cohortIds)
            ->with(['cohort', 'creator'])
            ->orderBy('due_date', 'desc')
            ->get();

        // Check if student has already submitted
        $assignments = $assignments->map(function($assignment) use ($user) {
            $submission = AssignmentSubmission::where('assignment_id', $assignment->id)
                ->where('student_id', $user->id)
                ->first();
            
            $assignment->my_submission = $submission;
            return $assignment;
        });

        return response()->json($assignments);
    }

    // Student: Submit assignment
    public function submit(Request $request, $id)
    {
        $request->validate([
            'answer_text' => 'nullable|string',
            'submission_file' => 'nullable|file|mimes:pdf,doc,docx,zip,jpg,jpeg,png|max:51200' // 50MB max
        ]);

        $assignment = Assignment::findOrFail($id);
        $user = Auth::user();
        
        if (!$request->hasFile('submission_file') && empty($request->answer_text)) {
            return response()->json(['message' => 'Please provide an answer text or upload a file.'], 400);
        }

        $path = null;
        $provider = 'local';
        if ($request->hasFile('submission_file')) {
            $bunnyService = new \App\Services\BunnyStorageService();
            $path = $bunnyService->upload($request->file('submission_file'), 'submissions');
            $provider = 'bunny';
        }
        
        $updateData = [
            'answer_text' => $request->answer_text,
            'submitted_at' => now(),
            'storage_provider' => $provider
        ];
        
        if ($path) {
            $updateData['submission_file'] = $path;
        }

        $submission = AssignmentSubmission::updateOrCreate(
            ['assignment_id' => $id, 'student_id' => $user->id],
            $updateData
        );

        $cohort = \App\Models\Cohort::find($assignment->cohort_id);
        if ($cohort && $submission->wasRecentlyCreated) {
            \App\Models\ActivityLog::create([
                'user_id' => $user->id,
                'instructor_id' => $cohort->instructor_id,
                'action' => 'assignment_submitted',
                'description' => $user->name . ' submitted assignment: ' . $assignment->title,
                'metadata' => ['assignment_id' => $assignment->id, 'cohort_id' => $cohort->id, 'submission_id' => $submission->id]
            ]);
        }

        return response()->json($submission, 201);
    }

    public function download($id)
    {
        $assignment = Assignment::findOrFail($id);
        
        if (!$assignment->assignment_file) {
            return response()->json(['message' => 'No file attached to this assignment.'], 404);
        }

        $path = $assignment->assignment_file;
        
        if (Storage::disk('s3')->exists($path)) {
            $disk = 's3';
        } elseif (Storage::disk('public')->exists($path)) {
            $disk = 'public';
        } else {
            return response()->json(['message' => 'Resource file missing from server.'], 404);
        }

        // Clean filename for the download
        $cleanTitle = preg_replace('/[^A-Za-z0-9\-]/', '_', $assignment->title);
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $fileName = $cleanTitle . '.' . $extension;

        return Storage::disk($disk)->download($path, $fileName);
    }

    // Instructor: Delete assignment
    public function destroy($id)
    {
        $assignment = Assignment::findOrFail($id);

        // Delete associated file if it exists
        if ($assignment->assignment_file) {
            if ($assignment->storage_provider === 'bunny') {
                $bunnyService = new \App\Services\BunnyStorageService();
                $bunnyService->delete($assignment->assignment_file);
            } elseif ($assignment->storage_provider === 'aws' || $assignment->storage_provider === 's3' || Storage::disk('s3')->exists($assignment->assignment_file)) {
                Storage::disk('s3')->delete($assignment->assignment_file);
            } else {
                Storage::disk('public')->delete($assignment->assignment_file);
            }
        }

        $assignment->delete();

        return response()->json(['message' => 'Assignment deleted successfully.']);
    }
}
