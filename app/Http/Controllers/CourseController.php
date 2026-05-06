<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Module;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Course::with(['instructor', 'modules.lessons']);
        
        if ($user && $user->role === 'instructor') {
            $query->where('instructor_id', $user->id);
        }
        
        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string',
            'target_level' => 'nullable|string',
            'language' => 'nullable|string',
            'duration' => 'nullable|string',
            'thumbnail' => 'nullable|string',
            'instructor_id' => 'required|exists:students,id',
            'modules' => 'nullable|array',
            'modules.*.title' => 'required|string',
            'modules.*.lessons' => 'nullable|array',
        ]);

        if (isset($validated['thumbnail']) && str_starts_with($validated['thumbnail'], 'data:image')) {
            $imageParts = explode(";base64,", $validated['thumbnail']);
            if (count($imageParts) == 2) {
                $imageTypeAux = explode("image/", $imageParts[0]);
                $imageType = $imageTypeAux[1];
                $imageBase64 = base64_decode($imageParts[1]);
                $fileName = 'course_thumbnails/' . uniqid() . '.' . $imageType;
                
                Storage::disk('s3')->put($fileName, $imageBase64);
                
                $validated['thumbnail'] = Storage::disk('s3')->url($fileName);
            } else {
                unset($validated['thumbnail']);
            }
        }

        return DB::transaction(function () use ($validated) {
            $course = Course::create($validated);

            if (isset($validated['modules'])) {
                foreach ($validated['modules'] as $modIndex => $modData) {
                    $module = $course->modules()->create([
                        'title' => $modData['title'],
                        'order' => $modIndex
                    ]);

                    if (isset($modData['lessons'])) {
                        foreach ($modData['lessons'] as $lessonIndex => $lessonData) {
                            $module->lessons()->create(array_merge($lessonData, [
                                'order' => $lessonIndex
                            ]));
                        }
                    }
                }
            }

            return response()->json($course->load('modules.lessons'), 201);
        });
    }

    public function show(Course $course)
    {
        return response()->json($course->load(['modules.lessons', 'cohorts']));
    }

    public function update(Request $request, Course $course)
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string',
            'target_level' => 'nullable|string',
            'language' => 'nullable|string',
            'duration' => 'nullable|string',
            'thumbnail' => 'nullable|string', // can be base64 string or an existing URL
        ]);

        if (isset($validated['thumbnail']) && str_starts_with($validated['thumbnail'], 'data:image')) {
            $imageParts = explode(";base64,", $validated['thumbnail']);
            if (count($imageParts) == 2) {
                $imageTypeAux = explode("image/", $imageParts[0]);
                $imageType = $imageTypeAux[1];
                $imageBase64 = base64_decode($imageParts[1]);
                $fileName = 'course_thumbnails/' . uniqid() . '.' . $imageType;
                
                Storage::disk('s3')->put($fileName, $imageBase64);
                
                $validated['thumbnail'] = Storage::disk('s3')->url($fileName);
                
                // Optional: Delete old thumbnail if exists and is S3
                if ($course->thumbnail && str_contains($course->thumbnail, 'course_thumbnails')) {
                    $oldPath = last(explode('/', parse_url($course->thumbnail, PHP_URL_PATH)));
                    $oldFullPath = 'course_thumbnails/' . $oldPath;
                    if (Storage::disk('s3')->exists($oldFullPath)) {
                        Storage::disk('s3')->delete($oldFullPath);
                    }
                }
            } else {
                unset($validated['thumbnail']);
            }
        }

        return DB::transaction(function () use ($validated, $course, $request) {
            $course->update($validated);
            
            if ($request->has('modules')) {
                $existingModuleIds = [];
                foreach ($request->input('modules') as $modIndex => $modData) {
                    if (isset($modData['id']) && is_numeric($modData['id'])) {
                        $module = $course->modules()->find($modData['id']);
                        if ($module) {
                            $module->update([
                                'title' => $modData['title'],
                                'order' => $modIndex
                            ]);
                        } else {
                            $module = $course->modules()->create([
                                'title' => $modData['title'],
                                'order' => $modIndex
                            ]);
                        }
                    } else {
                        $module = $course->modules()->create([
                            'title' => $modData['title'],
                            'order' => $modIndex
                        ]);
                    }
                    $existingModuleIds[] = $module->id;

                    if (isset($modData['lessons'])) {
                        $existingLessonIds = [];
                        foreach ($modData['lessons'] as $lessonIndex => $lessonData) {
                            $lessonAttributes = array_merge($lessonData, ['order' => $lessonIndex]);
                            unset($lessonAttributes['id']); 

                            if (isset($lessonData['id']) && is_numeric($lessonData['id'])) {
                                $lesson = $module->lessons()->find($lessonData['id']);
                                if ($lesson) {
                                    $lesson->update($lessonAttributes);
                                } else {
                                    $lesson = $module->lessons()->create($lessonAttributes);
                                }
                            } else {
                                $lesson = $module->lessons()->create($lessonAttributes);
                            }
                            $existingLessonIds[] = $lesson->id;
                        }
                        $module->lessons()->whereNotIn('id', $existingLessonIds)->delete();
                    } else {
                        $module->lessons()->delete();
                    }
                }
                $course->modules()->whereNotIn('id', $existingModuleIds)->delete();
            }

            if ($request->has('cohort_id')) {
                $cohort = \App\Models\Cohort::find($request->input('cohort_id'));
                if ($cohort) {
                    $cohort->update(['course_id' => $course->id]);
                }
            }

            // Recalculate progress for all students enrolled in this course
            $course->recalculateAllStudentsProgress();

            return response()->json($course->load(['modules.lessons', 'cohorts']));
        });
    }

    public function destroy(Course $course)
    {
        $course->delete();
        return response()->json(null, 204);
    }

    public function generateBunnyUploadSignature(Request $request)
    {
        try {
            $title = $request->input('title', 'Untitled Video');
            $bunnyService = new \App\Services\BunnyStreamService();
            $prep = $bunnyService->prepareDirectUpload($title);

            if (!$prep) {
                return response()->json(['success' => false, 'message' => 'Failed to prepare Bunny upload'], 500);
            }

            return response()->json([
                'success' => true,
                ...$prep
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function uploadVideo(Request $request)
    {
        try {
            // Early detection: If Content-Length is high but files are empty, PHP limits were likely reached.
            if ($request->header('Content-Length') > 0 && empty($request->allFiles())) {
                return response()->json([
                    'success' => false,
                    'message' => 'The file size exceeds the server\'s current limit. Increasing server limits in php.ini (post_max_size, upload_max_filesize) may resolve this.'
                ], 413);
            }

            $request->validate([
                'video' => 'required|file', // max depends on Server/Bunny
            ]);

            if ($request->hasFile('video')) {
                $file = $request->file('video');
                $extension = strtolower($file->getClientOriginalExtension() ?: 'mp4');
                
                // Content type organization
                $videoExts = ['mp4', 'mkv', 'mov', 'avi', 'webm', 'm4v'];
                
                if (in_array($extension, $videoExts)) {
                    $bunnyService = new \App\Services\BunnyStreamService();
                    $videoUrl = $bunnyService->upload($file);
                } else {
                    $directory = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']) ? 'course_images' : 'course_documents';
                    $bunnyService = new \App\Services\BunnyStorageService();
                    $videoUrl = $bunnyService->upload($file, $directory);
                }
                
                if (!$videoUrl) {
                    throw new \Exception("Upload to Bunny.net failed.");
                }

                return response()->json([
                    'success' => true,
                    'video_url' => $videoUrl,
                    'storage_provider' => 'bunny'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No file provided'
            ], 400);

        } catch (\Exception $e) {
            \Log::error('Video Upload Error: ' . $e->getMessage(), [
                'stack' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteAsset(Request $request)
    {
        \Log::info('Delete request received', $request->all());

        try {
            $filePath = $request->input('path') ?? $request->input('file_path');

            if (!$filePath) {
                return response()->json(['error' => 'No file path provided'], 400);
            }

            // Normalize path (remove /storage if present)
            $filePath = str_replace(['/storage/', 'storage/'], '', $filePath);
            $filePath = ltrim($filePath, '/');

            // Delete from public if exists
            if (\Storage::disk('public')->exists($filePath)) {
                \Storage::disk('public')->delete($filePath);
            }

            // Delete from s3 if exists
            if (\Storage::disk('s3')->exists($filePath)) {
                \Storage::disk('s3')->delete($filePath);
            }

            return response()->json([
                'success' => true,
                'message' => 'File deleted successfully (or was already removed)'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Delete failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function fixStorageLink()
    {
        try {
            $publicPath = public_path('storage');
            $storagePath = storage_path('app/public');
            $results = [];

            // 1. Initial State Check
            if (file_exists($publicPath)) {
                if (is_link($publicPath)) {
                    $results[] = "Existing link detected.";
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'A physical folder named "storage" exists in public/. Please rename or delete it so a symlink can be created.'
                    ], 400);
                }
            } else {
                // 2. Attempt Symlink Creation
                if (function_exists('symlink')) {
                    @symlink($storagePath, $publicPath);
                    $results[] = "Created via symlink().";
                } else {
                    \Illuminate\Support\Facades\Artisan::call('storage:link');
                    $results[] = "Created via Artisan storage:link.";
                }
            }

            // 3. Verification Test
            $testFile = 'link_test_' . time() . '.txt';
            $testMsg = "Verification successful at " . date('Y-m-d H:i:s');
            
            Storage::disk('public')->put($testFile, $testMsg);
            
            $testUrl = url('storage/' . $testFile);
            $localCheckPath = public_path('storage/' . $testFile);
            
            $fileReadable = file_exists($localCheckPath);
            $contentMatch = $fileReadable && (file_get_contents($localCheckPath) === $testMsg);

            // Cleanup
            Storage::disk('public')->delete($testFile);

            return response()->json([
                'success' => $contentMatch,
                'message' => $contentMatch ? 'Storage link is ACTIVE and WORKING.' : 'Link created but FILE NOT READABLE. Check permissions.',
                'details' => [
                    'methods_tried' => $results,
                    'test_file_path' => $localCheckPath,
                    'is_readable' => $fileReadable,
                    'content_match' => $contentMatch,
                    'public_path' => $publicPath,
                    'target_path' => $storagePath
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fatal error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function listVideos()
    {
        try {
            $videos = [];

            // 1. Local Public Videos
            $directory = 'course_videos';
            if (Storage::disk('public')->exists($directory)) {
                $files = Storage::disk('public')->files($directory);
                foreach ($files as $file) {
                    $path = str_replace('course_videos/', '', $file);
                    $url = url('storage/' . $file);
                    
                    // Force Production URL and HTTPS for shared hosting
                    if (str_contains($url, 'localhost') || str_contains($url, '127.0.0.1')) {
                        $url = str_replace(['http://localhost:8000', 'http://127.0.0.1:8000'], 'https://layosgroupllc.com/backend', $url);
                    }
                    
                    if (str_contains($url, 'layosgroupllc.com')) {
                        $url = str_replace('http://', 'https://', $url);
                    }

                    $videos[] = [
                        'name' => $path,
                        'url' => $url,
                        'size' => $this->formatBytes(Storage::disk('public')->size($file)),
                        'last_modified' => date('Y-m-d H:i:s', Storage::disk('public')->lastModified($file)),
                        'source' => 'local'
                    ];
                }
            }

            // 2. Bunny Stream Videos
            try {
                $bunnyService = new \App\Services\BunnyStreamService();
                $bunnyData = $bunnyService->listVideos();
                
                if (isset($bunnyData['items'])) {
                    $libraryId = env('BUNNY_STREAM_LIBRARY_ID');
                    foreach ($bunnyData['items'] as $item) {
                        $dateStr = $item['dateUploaded'] ?? $item['dateCreated'] ?? 'now';
                        $videos[] = [
                            'name' => ($item['title'] ?: 'Untitled Video') . ' (Bunny Stream)',
                            'url' => "https://iframe.mediadelivery.net/play/{$libraryId}/{$item['guid']}",
                            'size' => $this->formatDuration($item['length']),
                            'last_modified' => date('Y-m-d H:i:s', strtotime($dateStr)),
                            'source' => 'bunny'
                        ];
                    }
                    \Log::info("Bunny Stream: Found " . count($bunnyData['items']) . " videos.");
                } else {
                    \Log::warning("Bunny Stream: No items found in response.");
                }
            } catch (\Exception $be) {
                \Log::error("Bunny List Error in Controller: " . $be->getMessage());
            }

            // Sort by last modified descending
            usort($videos, function($a, $b) {
                return strtotime($b['last_modified']) - strtotime($a['last_modified']);
            });

            return response()->json($videos);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    private function formatDuration($seconds)
    {
        if (!$seconds) return '0s';
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = $seconds % 60;
        
        $parts = [];
        if ($h > 0) $parts[] = $h . 'h';
        if ($m > 0) $parts[] = $m . 'm';
        if ($s > 0 || empty($parts)) $parts[] = $s . 's';
        
        return implode(' ', $parts);
    }
}
