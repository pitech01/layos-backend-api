<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LiveSessionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if ($user->role === 'instructor') {
            $courseIds = \App\Models\Course::where('instructor_id', $user->id)->pluck('id');
            return \App\Models\LiveSession::whereIn('course_id', $courseIds)->with('course')->get();
        }
        return \App\Models\LiveSession::with('course')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'course_id' => 'required|exists:courses,id',
            'scheduled_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'meeting_link' => 'nullable|url',
            'recording_link' => 'nullable|url',
            'recording_file' => 'nullable|file|mimes:mp4,webm,ogg,mov|max:2097152', // max 2GB
        ]);

        if ($request->hasFile('recording_file')) {
            $file = $request->file('recording_file');
            $fileName = \Illuminate\Support\Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('live_recordings', $fileName, 'public');
            $validated['recording_link'] = \Illuminate\Support\Facades\Storage::disk('public')->url($path);
        }

        $instructorName = $request->user()->name;
        unset($validated['recording_file']);
        $session = \App\Models\LiveSession::create(array_merge($validated, ['instructor_name' => $instructorName]));

        return response()->json($session, 201);
    }

    public function update(Request $request, \App\Models\LiveSession $liveSession)
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'course_id' => 'sometimes|exists:courses,id',
            'scheduled_date' => 'sometimes|date',
            'start_time' => 'sometimes',
            'end_time' => 'sometimes',
            'meeting_link' => 'sometimes|url',
            'recording_link' => 'nullable|url',
            'recording_file' => 'nullable|file|mimes:mp4,webm,ogg,mov|max:2097152', // max 2GB
        ]);

        if ($request->hasFile('recording_file')) {
            // Delete old recording if exists
            if ($liveSession->recording_link && str_contains($liveSession->recording_link, 'live_recordings')) {
                // Determine which disk to delete from
                $oldDisk = str_contains($liveSession->recording_link, '/storage/') ? 'public' : 's3';
                
                // Extract path
                if ($oldDisk === 'public') {
                    $parts = explode('/storage/', $liveSession->recording_link);
                    $oldPath = urldecode(explode('?', end($parts))[0]);
                } else {
                    $oldPath = 'live_recordings/' . last(explode('/', parse_url($liveSession->recording_link, PHP_URL_PATH)));
                }

                if (\Illuminate\Support\Facades\Storage::disk($oldDisk)->exists($oldPath)) {
                    \Illuminate\Support\Facades\Storage::disk($oldDisk)->delete($oldPath);
                }
            }

            $file = $request->file('recording_file');
            $fileName = \Illuminate\Support\Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('live_recordings', $fileName, 'public');
            $validated['recording_link'] = \Illuminate\Support\Facades\Storage::disk('public')->url($path);
        }

        unset($validated['recording_file']);
        $liveSession->update($validated);

        return response()->json($liveSession);
    }

    public function studentSessions(Request $request)
    {
        $user = $request->user();
        $courseIds = $user->cohorts()
            ->wherePivot('status', '!=', 'dropped')
            ->pluck('cohorts.course_id')
            ->filter();
        
        $sessions = \App\Models\LiveSession::whereIn('course_id', $courseIds)
            ->with('course')
            ->orderBy('scheduled_date', 'desc')
            ->get();
            
        return response()->json($sessions);
    }

    public function destroy(\App\Models\LiveSession $liveSession)
    {
        $liveSession->delete();
        return response()->json(null, 204);
    }
}
