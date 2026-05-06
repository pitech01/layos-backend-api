<?php

namespace App\Http\Controllers;

use App\Models\DirectMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Events\NewDirectMessage;

class DirectMessageController extends Controller
{
    public function index(Request $request, $userId)
    {
        $senderId = $request->user()->id;
        
        $messages = DirectMessage::where(function($q) use ($senderId, $userId) {
                $q->where('sender_id', $senderId)->where('receiver_id', $userId);
            })->orWhere(function($q) use ($senderId, $userId) {
                $q->where('sender_id', $userId)->where('receiver_id', $senderId);
            })
            ->with(['sender:id,name', 'receiver:id,name'])
            ->oldest()
            ->get();
 
        // Mark as read
        DirectMessage::where('sender_id', $userId)
            ->where('receiver_id', $senderId)
            ->where('is_read', false)
            ->update(['is_read' => true]);
 
        return response()->json($messages->map(fn($msg) => [
            'id' => (string)$msg->id,
            'senderName' => $msg->sender->name,
            'senderId' => $msg->sender_id,
            'senderRole' => $msg->sender->role,
            'content' => $msg->is_deleted ? 'This message has been deleted.' : $msg->content,
            'attachmentUrl' => $msg->attachment_url,
            'attachmentName' => $msg->attachment_name,
            'isDeleted' => (bool)$msg->is_deleted,
            'createdAt' => $msg->created_at->toISOString()
        ]));
    }
 
    public function store(Request $request, $userId)
    {
        $request->validate([
            'content' => 'required|string',
            'attachment' => 'nullable|file|max:10240'
        ]);
 
        $attachmentUrl = null;
        $attachmentName = null;
        $provider = 'local';
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $bunnyService = new \App\Services\BunnyStorageService();
            $attachmentUrl = $bunnyService->upload($file, 'dm_attachments');
            $attachmentName = $file->getClientOriginalName();
            $provider = 'bunny';
        }
 
        $message = DirectMessage::create([
            'sender_id' => $request->user()->id,
            'receiver_id' => $userId,
            'content' => $request->input('content'),
            'attachment_url' => $attachmentUrl,
            'attachment_name' => $attachmentName,
            'storage_provider' => $provider
        ]);
 
        $formatted = [
            'id' => (string)$message->id,
            'senderName' => $request->user()->name,
            'senderId' => $message->sender_id,
            'senderRole' => $request->user()->role,
            'content' => $message->content,
            'attachmentUrl' => $message->attachment_url,
            'attachmentName' => $message->attachment_name,
            'isDeleted' => false,
            'createdAt' => $message->created_at->toISOString()
        ];

        try {
            broadcast(new NewDirectMessage($formatted, $userId))->toOthers();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("DM Broadcasting failed: " . $e->getMessage());
        }

        return response()->json($formatted, 201);
    }

    public function contacts(Request $request)
    {
        $userId = $request->user()->id;
        
        // Find users who I have sent to or received from
        $contactIds = DirectMessage::where('sender_id', $userId)
            ->orWhere('receiver_id', $userId)
            ->select('sender_id', 'receiver_id')
            ->get()
            ->flatMap(fn($m) => [$m->sender_id, $m->receiver_id])
            ->unique()
            ->reject(fn($id) => $id == $userId);

        $contacts = User::whereIn('id', $contactIds)
            ->select('id', 'name', 'role')
            ->get()
            ->map(function($user) use ($userId) {
                $unreadCount = DirectMessage::where('sender_id', $user->id)
                    ->where('receiver_id', $userId)
                    ->where('is_read', false)
                    ->count();
                return [
                    'id' => (string)$user->id,
                    'title' => $user->name,
                    'type' => 'dm',
                    'unread' => $unreadCount,
                    'status' => 'online', // Mock for now
                    'avatar' => strtoupper(substr($user->name, 0, 1)),
                    'role' => $user->role
                ];
            });

        return response()->json($contacts);
    }

    public function searchUsers(Request $request)
    {
        $query = $request->input('q');
        if (strlen($query) < 2) return response()->json([]);

        $users = User::where('name', 'LIKE', "%{$query}%")
            ->where('id', '!=', $request->user()->id)
            ->select('id', 'name', 'role')
            ->limit(10)
            ->get()
            ->map(fn($u) => [
                'id' => (string)$u->id,
                'title' => $u->name,
                'type' => 'dm',
                'role' => $u->role
            ]);

        return response()->json($users);
    }

    public function destroy(Request $request, $userId, $messageId)
    {
        $message = DirectMessage::findOrFail($messageId);
        
        if ((string)$message->sender_id !== (string)$request->user()->id && $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $message->update(['is_deleted' => true]);

        try {
            broadcast(new \App\Events\DirectMessageDeleted($message->id, $userId))->toOthers();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("DM Delete Broadcasting failed: " . $e->getMessage());
        }

        return response()->json(['message' => 'Message deleted successfully', 'id' => $messageId]);
    }
}
