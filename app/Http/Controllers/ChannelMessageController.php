<?php

namespace App\Http\Controllers;

use App\Models\ChannelMessage;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Events\NewChannelMessage;

class ChannelMessageController extends Controller
{
    public function index(Request $request, $courseId)
    {
        $user = $request->user();
        
        // Ensure user can access the channel (either instructor of the course, or enrolled student)
        $course = Course::findOrFail($courseId);
        
        // Instructors can only see their own courses (unless we want admins too)
        if ($user->role === 'instructor' && $course->instructor_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        // Students can only see channels for courses they are enrolled in
        if ($user->role === 'student') {
            $isEnrolled = $user->cohorts()
                ->wherePivot('status', '!=', 'dropped')
                ->where('course_id', $courseId)
                ->exists();
            if (!$isEnrolled) {
                return response()->json(['message' => 'Unauthorized. Access revoked or not enrolled.'], 403);
            }
        }
        
        $messages = ChannelMessage::with('user:id,name,role')
            ->where('course_id', $courseId)
            ->oldest() // Slack usually loads oldest first or newest first depending on how UI is built, let's just get everything for now, newest at bottom
            ->get();
            
        // Map to what frontend expects
        $formatted = $messages->map(function ($msg) {
            return [
                'id' => (string)$msg->id,
                'senderId' => (string)$msg->user_id,
                'senderName' => $msg->user->name ?? 'Unknown User',
                'senderRole' => $msg->user->role ?? 'student',
                'type' => $msg->type,
                'content' => $msg->is_deleted ? 'This message has been deleted.' : $msg->content,
                'dueDate' => $msg->due_date,
                'attachmentUrl' => $msg->attachment_url,
                'attachmentName' => $msg->attachment_name,
                'isDeleted' => (bool)$msg->is_deleted,
                'createdAt' => $msg->created_at->toISOString()
            ];
        });

        return response()->json([
            'courseTitle' => $course->title,
            'messages' => $formatted
        ]);
    }

    public function indexByChannel(Request $request, $channelId)
    {
        $user = $request->user();
        $channel = \App\Models\CourseChannel::findOrFail($channelId);
        
        // Authorization: Enrolled student or Course Instructor
        if ($user->role === 'student') {
            if ($channel->course_id) {
                $isEnrolled = $user->cohorts()
                    ->wherePivot('status', '!=', 'dropped')
                    ->where('course_id', $channel->course_id)
                    ->exists();
                if (!$isEnrolled) return response()->json(['message' => 'Unauthorized'], 403);
            }
            // If course_id is null, it's a global channel accessible to all students
        } elseif ($user->role === 'instructor') {
            $course = Course::find($channel->course_id);
            if ($course && $course->instructor_id !== $user->id) return response()->json(['message' => 'Unauthorized'], 403);
        }

        $messages = ChannelMessage::with('user:id,name,role')
            ->where('channel_id', $channelId)
            ->oldest()
            ->get();

        $formatted = $messages->map(function ($msg) {
             return [
                 'id' => (string)$msg->id,
                 'senderId' => (string)$msg->user_id,
                 'senderName' => $msg->user->name ?? 'Unknown User',
                 'senderRole' => $msg->user->role ?? 'student',
                 'type' => $msg->type,
                 'content' => $msg->is_deleted ? 'This message has been deleted.' : $msg->content,
                 'attachmentUrl' => $msg->attachment_url,
                 'attachmentName' => $msg->attachment_name,
                 'isDeleted' => (bool)$msg->is_deleted,
                 'createdAt' => $msg->created_at->toISOString()
             ];
        });

        return response()->json([
            'channelTitle' => $channel->name,
            'messages' => $formatted
        ]);
    }

    public function storeByChannel(Request $request, $channelId)
    {
        $user = $request->user();
        $channel = \App\Models\CourseChannel::findOrFail($channelId);
        $courseId = $channel->course_id;

        // Authorization: Enrolled student or Course Instructor
        if ($user->role === 'student') {
            if ($courseId) {
                $isEnrolled = $user->cohorts()
                    ->wherePivot('status', '!=', 'dropped')
                    ->where('course_id', $courseId)
                    ->exists();
                if (!$isEnrolled) return response()->json(['message' => 'Unauthorized'], 403);
            }
        } elseif ($user->role === 'instructor') {
            if ($courseId) {
                $course = Course::find($courseId);
                if ($course && $course->instructor_id !== $user->id) return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $request->validate([
            'content' => 'required|string',
            'attachment' => 'nullable|file|max:102400'
        ]);

        $attachmentPath = null;
        $attachmentName = null;
        $provider = 'local';
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $bunnyService = new \App\Services\BunnyStorageService();
            $attachmentPath = $bunnyService->upload($file, 'channel_attachments');
            $attachmentName = $file->getClientOriginalName();
            $provider = 'bunny';
        }

        $message = ChannelMessage::create([
            'channel_id' => $channelId,
            'course_id' => $courseId,
            'user_id' => $user->id,
            'content' => $request->input('content'),
            'type' => $request->input('type', 'message'),
            'attachment_url' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'storage_provider' => $provider
        ]);

        $formatted = [
            'id' => (string)$message->id,
            'senderId' => (string)$user->id,
            'senderName' => $user->name,
            'senderRole' => $user->role,
            'type' => $message->type,
            'content' => $message->content,
            'attachmentUrl' => $message->attachment_url,
            'attachmentName' => $message->attachment_name,
            'isDeleted' => false,
            'createdAt' => $message->created_at->toISOString()
        ];

        // Broadcast the new message
        try {
            broadcast(new \App\Events\NewChannelMessage($formatted, $channelId))->toOthers();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Broadcasting failed: " . $e->getMessage());
        }

        return response()->json($formatted, 201);
    }

    public function store(Request $request, $courseId)
    {
        $user = $request->user();
        
        // Validation
        $request->validate([
            'content' => 'required|string',
            'type' => 'sometimes|in:message,announcement,assignment',
            'due_date' => 'sometimes|nullable|date',
            'attachment' => 'sometimes|nullable|file|max:51200' // max 50MB
        ]);

        $course = Course::findOrFail($courseId);
        
        if ($user->role === 'instructor' && $course->instructor_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        if ($user->role === 'student') {
            $isEnrolled = $user->cohorts()
                ->wherePivot('status', '!=', 'dropped')
                ->where('course_id', $courseId)
                ->exists();
            if (!$isEnrolled) {
                return response()->json(['message' => 'Unauthorized. Access revoked or not enrolled.'], 403);
            }
        }

        $type = $request->input('type', 'message');
        
        // Only instructors can post announcements or assignments
        if ($user->role === 'student' && $type !== 'message') {
            $type = 'message';
        }

        $attachmentPath = null;
        $attachmentName = null;
        $provider = 'local';
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $bunnyService = new \App\Services\BunnyStorageService();
            $attachmentPath = $bunnyService->upload($file, 'channel_attachments');
            $attachmentName = $file->getClientOriginalName();
            $provider = 'bunny';
        } elseif ($request->input('attachmentUrl')) {
             // Fallback if frontend sends URL directly (though we prefer path)
             $attachmentPath = $request->input('attachmentUrl');
             $attachmentName = basename($attachmentPath);
        }

        $message = ChannelMessage::create([
            'course_id' => $course->id,
            'user_id' => $user->id,
            'content' => $request->input('content'),
            'type' => $type,
            'attachment_url' => $attachmentPath,
            'attachment_name' => $attachmentName ?? null,
            'due_date' => $request->input('due_date'),
            'storage_provider' => $provider
        ]);
        
        $message->load('user:id,name,role');

        $formatted = [
            'id' => (string)$message->id,
            'senderId' => (string)$message->user_id,
            'senderName' => $message->user->name,
            'senderRole' => $message->user->role,
            'type' => $message->type,
            'content' => $message->content,
            'dueDate' => $message->due_date,
            'attachmentUrl' => $message->attachment_url,
            'attachmentName' => $message->attachment_name,
            'isDeleted' => false,
            'createdAt' => $message->created_at->toISOString()
        ];

        broadcast(new NewChannelMessage($formatted, $courseId))->toOthers();

        return response()->json($formatted, 201);
    }

    public function update(Request $request, $messageId)
    {
        $message = ChannelMessage::findOrFail($messageId);
        $user = $request->user();

        // Only the sender can edit their message
        if ((string)$message->user_id !== (string)$user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'content' => 'required|string',
        ]);

        $message->update([
            'content' => $request->input('content')
        ]);

        $message->load('user:id,name,role');

        $formatted = [
            'id' => (string)$message->id,
            'senderId' => (string)$message->user_id,
            'senderName' => $message->user->name,
            'senderRole' => $message->user->role,
            'type' => $message->type,
            'content' => $message->content,
            'attachmentUrl' => $message->attachment_url,
            'attachmentName' => $message->attachment_name,
            'isEdited' => true,
            'isDeleted' => false,
            'createdAt' => $message->created_at->toISOString()
        ];

        // Broadcast "MessageEdited" if we had an event class. For now, since the frontend replaces on match
        // and we don't have a NewChannelMessage that matches updates vs creates out-of-the-box easily without an Event
        // we can just re-send NewChannelMessage or a specific MessageEdited event if one exists.
        // Wait, broadcast(new \App\Events\NewChannelMessage($formatted, $courseId))->toOthers();
        try {
            $broadcastChannelId = $message->channel_id ?: ($message->course_id ?: 'general');
            broadcast(new \App\Events\NewChannelMessage($formatted, (string)$broadcastChannelId))->toOthers();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Broadcasting failed for update: " . $e->getMessage());
        }

        return response()->json($formatted, 200);
    }

    public function destroy(Request $request, $messageId)
    {
        $message = ChannelMessage::findOrFail($messageId);
        $user = $request->user();
        
        // Authorization:
        // 1. Sender can delete their own message
        // 2. Course instructor can delete any message in their course
        // 3. Admin can delete any message
        
        $canDelete = false;
        if ((string)$message->user_id === (string)$user->id) {
            $canDelete = true;
        } elseif ($user->role === 'instructor') {
            // Find course through message or channel
            $courseId = $message->course_id;
            if (!$courseId && $message->channel_id) {
                $chan = \App\Models\CourseChannel::find($message->channel_id);
                $courseId = $chan?->course_id;
            }
            if ($courseId) {
                $course = Course::find($courseId);
                if ($course && (string)$course->instructor_id === (string)$user->id) {
                    $canDelete = true;
                }
            } else {
                $canDelete = true;
            }
        } elseif ($user->role === 'admin') {
            $canDelete = true;
        }

        if (!$canDelete) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $message->update(['is_deleted' => true]);

        // Broadcast to specific course channel or general channel
        try {
            $broadcastChannelId = $message->channel_id ?: ($message->course_id ?: 'general');
            broadcast(new \App\Events\ChannelMessageDeleted($messageId, (string)$broadcastChannelId))->toOthers();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Broadcasting failed for delete: " . $e->getMessage());
        }

        return response()->json(['message' => 'Message deleted successfully', 'id' => $messageId]);
    }
}
