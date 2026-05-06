<?php

namespace App\Http\Controllers;

use App\Models\ChannelMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GeneralChannelController extends Controller
{
    public function index(Request $request)
    {
        $messages = ChannelMessage::with('user:id,name,role')
            ->whereNull('course_id')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($msg) => [
                'id' => (string)$msg->id,
                'senderId' => (string)$msg->user_id,
                'senderName' => $msg->user->name ?? 'Unknown',
                'senderRole' => $msg->user->role ?? 'student',
                'type' => $msg->type,
                'content' => $msg->content,
                'attachmentUrl' => $msg->attachment_url,
                'attachmentName' => $msg->attachment_name,
                'createdAt' => $msg->created_at->toISOString()
            ]);

        return response()->json([
            'courseTitle' => 'General Discussion',
            'messages' => $messages
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'content' => 'required|string',
            'type' => 'sometimes|in:message,announcement',
            'attachment' => 'nullable|file|max:10240'
        ]);

        $attachmentUrl = null;
        $attachmentName = null;
        $provider = 'local';
        if ($request->hasFile('attachment')) {
            $bunnyService = new \App\Services\BunnyStorageService();
            $attachmentUrl = $bunnyService->upload($request->file('attachment'), 'general_attachments');
            $attachmentName = $request->file('attachment')->getClientOriginalName();
            $provider = 'bunny';
        }

        $message = ChannelMessage::create([
            'course_id' => null,
            'user_id' => $request->user()->id,
            'content' => $request->input('content'),
            'type' => $request->input('type', 'message'),
            'attachment_url' => $attachmentUrl,
            'attachment_name' => $attachmentName,
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
            'attachmentUrl' => $message->attachment_url,
            'attachmentName' => $message->attachment_name,
            'createdAt' => $message->created_at->toISOString()
        ];

        try {
            broadcast(new \App\Events\NewChannelMessage($formatted, 'general'))->toOthers();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("General Channel Broadcasting failed: " . $e->getMessage());
        }

        return response()->json($formatted, 201);
    }
}
