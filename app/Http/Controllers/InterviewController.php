<?php

namespace App\Http\Controllers;

use App\Models\Interview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class InterviewController extends Controller
{
    // Instructor: List all interviews
    public function index()
    {
        $interviews = Interview::with(['cohort'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($interviews);
    }

    // Instructor: Create interview
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'cohort_id' => 'nullable|exists:cohorts,id',
                'document' => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip|max:51200', // 50MB
                'video' => 'nullable|file|mimes:mp4,mov,avi,wmv,mkv|max:512000' // 500MB
            ]);

            $data = [
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'cohort_id' => $validated['cohort_id'] ?? null,
                'created_by' => Auth::id(),
                'storage_provider' => 'bunny', // New uploads to bunny
            ];

            if ($request->hasFile('document')) {
                $bunnyService = new \App\Services\BunnyStorageService();
                $path = $bunnyService->upload($request->file('document'), 'interviews/documents');
                if (!$path) throw new \Exception('Failed to upload document to storage.');
                $data['document_path'] = $path;
            }

            if ($request->hasFile('video')) {
                $bunnyService = new \App\Services\BunnyStreamService();
                $path = $bunnyService->upload($request->file('video'));
                if (!$path) throw new \Exception('Failed to upload video to stream storage.');
                $data['video_path'] = $path;
            }

            $interview = Interview::create($data);

            return response()->json($interview, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Upload failed: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        return response()->json(Interview::with('cohort')->findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        try {
            $interview = Interview::findOrFail($id);
            
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'cohort_id' => 'nullable|exists:cohorts,id',
                'document' => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip|max:51200',
                'video' => 'nullable|file|mimes:mp4,mov,avi,wmv,mkv|max:512000'
            ]);

            $data = [
                'title' => $validated['title'],
                'description' => $validated['description'] ?? $interview->description,
                'cohort_id' => $validated['cohort_id'] ?? null,
            ];

            if ($request->hasFile('document')) {
                // Delete old document
                if ($interview->document_path) {
                    if ($interview->storage_provider === 'bunny') {
                        (new \App\Services\BunnyStorageService())->delete($interview->document_path);
                    } elseif (Storage::disk('s3')->exists($interview->document_path)) {
                        Storage::disk('s3')->delete($interview->document_path);
                    } else {
                        Storage::disk('public')->delete($interview->document_path);
                    }
                }

                $bunnyService = new \App\Services\BunnyStorageService();
                $path = $bunnyService->upload($request->file('document'), 'interviews/documents');
                if (!$path) throw new \Exception('Failed to upload document to storage.');
                $data['document_path'] = $path;
                $data['storage_provider'] = 'bunny';
            }

            if ($request->hasFile('video')) {
                // Delete old video
                if ($interview->video_path) {
                    if ($interview->storage_provider === 'bunny') {
                        // For bunny stream, we'd need stream API to delete. 
                        // For now we just replace the path in DB.
                    } elseif (Storage::disk('s3')->exists($interview->video_path)) {
                        Storage::disk('s3')->delete($interview->video_path);
                    } else {
                        Storage::disk('public')->delete($interview->video_path);
                    }
                }

                $bunnyService = new \App\Services\BunnyStreamService();
                $path = $bunnyService->upload($request->file('video'));
                if (!$path) throw new \Exception('Failed to upload video to stream storage.');
                $data['video_path'] = $path;
                $data['storage_provider'] = 'bunny';
            }

            $interview->update($data);

            return response()->json($interview);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Update failed: ' . $e->getMessage()], 500);
        }
    }

    public function studentInterviews()
    {
        $user = Auth::user();
        $cohortIds = $user->cohorts()->pluck('cohorts.id');

        $interviews = Interview::whereIn('cohort_id', $cohortIds)
            ->orWhereNull('cohort_id')
            ->with(['cohort', 'creator'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($interviews);
    }

    public function destroy($id)
    {
        $interview = Interview::findOrFail($id);

        // Delete Document
        if ($interview->document_path) {
            if ($interview->storage_provider === 'bunny') {
                (new \App\Services\BunnyStorageService())->delete($interview->document_path);
            } elseif (Storage::disk('s3')->exists($interview->document_path)) {
                Storage::disk('s3')->delete($interview->document_path);
            } else {
                Storage::disk('public')->delete($interview->document_path);
            }
        }

        // Delete Video (Bunny Stream doesn't need manual deletion via storage API usually, 
        // but if it's a direct file path, we handle it)
        if ($interview->video_path) {
            if ($interview->storage_provider === 'bunny') {
                // Video path for bunny stream is a GUID, manual deletion would need stream API
                // For now we assume the database record deletion is enough or add stream delete if needed
            } elseif (Storage::disk('s3')->exists($interview->video_path)) {
                Storage::disk('s3')->delete($interview->video_path);
            } else {
                Storage::disk('public')->delete($interview->video_path);
            }
        }

        $interview->delete();

        return response()->json(['message' => 'Interview deleted successfully.']);
    }
}
