<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'instructor' && $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = ActivityLog::with('user');
        
        if ($user->role === 'instructor') {
            $query->where('instructor_id', $user->id);
        }

        $logs = $query->latest()->take(100)->get();

        return response()->json($logs);
    }
}
