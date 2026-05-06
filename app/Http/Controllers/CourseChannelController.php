<?php

namespace App\Http\Controllers;

use App\Models\CourseChannel;
use Illuminate\Http\Request;

class CourseChannelController extends Controller
{
    public function index(Request $request, $courseId = null)
    {
        $user = $request->user();
        $query = CourseChannel::query();
        
        if ($courseId) {
             if ($courseId === 'general') {
                 $query->whereNull('course_id');
             } else {
                 $query->where('course_id', $courseId);
             }
        } else {
            // Fetch all channels the user has access to
            if ($user->role === 'instructor') {
                $myCourseIds = \App\Models\Course::where('instructor_id', $user->id)->pluck('id');
                $query->whereIn('course_id', $myCourseIds)->orWhereNull('course_id');
            } else {
                // The user requested that all channels created should be accessible by the student.
                // No additional filtering is applied.
            }
        }

        $channels = $query->orderBy('name')->get()->map(function ($channel) {
            $latestMessage = $channel->messages()->latest()->first();
            $channel->latest_message_id = $latestMessage ? $latestMessage->id : null;
            return $channel;
        });

        return response()->json($channels);
    }

    public function store(Request $request, $courseId = null)
    {
        $request->validate([
            'name' => 'required|string|max:50',
            'description' => 'nullable|string|max:255',
            'type' => 'nullable|string'
        ]);

        $channel = CourseChannel::create([
            'course_id' => ($courseId === 'general' ? null : $courseId),
            'name' => strtolower(str_replace(' ', '-', $request->name)),
            'description' => $request->description,
            'type' => $request->type ?? 'public',
            'created_by' => $request->user()->id
        ]);

        return response()->json($channel, 201);
    }

    public function destroy(Request $request, $id)
    {
        $channel = CourseChannel::findOrFail($id);
        
        if ($request->user()->role !== 'instructor') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $channel->delete();

        return response()->json(['message' => 'Channel deleted successfully']);
    }
}
