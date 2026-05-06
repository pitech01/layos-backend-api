<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\CohortController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CertificateTemplateController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

/*
// Manual CORS Header Fallback for Shared Hosting
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $origin = $_SERVER['HTTP_ORIGIN'];
    $allowed_origins = [
        'http://localhost:5173',
        'https://layosgroup-imlj.vercel.app',
        'https://layosgroupllc.com'
    ];
    if (in_array($origin, $allowed_origins)) {
        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-XSRF-TOKEN");
        header("Access-Control-Allow-Credentials: true");
    }
}

// Handle OPTIONS preflight immediately for all routes
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}
*/

Route::post('/login', [AuthController::class, 'login']);
Route::post('/instructor/login', [AuthController::class, 'instructorLogin']);
Route::post('/register', [AuthController::class, 'register']);

// Emergency Cache Fix
Route::get('/fix-cache', function() {
    try {
        \Illuminate\Support\Facades\Artisan::call('config:clear');
        \Illuminate\Support\Facades\Artisan::call('route:clear');
        \Illuminate\Support\Facades\Artisan::call('cache:clear');
        return "Cache cleared successfully!";
    } catch (\Exception $e) {
        return "Error clearing cache: " . $e->getMessage();
    }
});

// Debug Route to verify API is active
Route::get('/test-route', function () {
    return response()->json(['status' => 'working']);
});

// PDF Proxy with Deep Search
Route::get('/pdf-proxy', function (Illuminate\Http\Request $request) {
    $url = $request->query('url');
    if (!$url) {
        return response()->json(['error' => 'Missing url parameter'], 400);
    }

    if (str_contains($url, '/storage/')) {
        $rawPath = explode('/storage/', $url)[1];
        $rawPath = explode('?', $rawPath)[0];
        $cleanPath = urldecode($rawPath);
        $fileName = basename($cleanPath);

        $disks = ['public', 'local'];
        $potentialPaths = [
            $cleanPath, 
            'interviews/documents/' . $fileName, 
            'interviews/videos/' . $fileName,
            'course_documents/' . $fileName, 
            'course_assets/' . $fileName, 
            $fileName
        ];

        foreach ($disks as $diskName) {
            foreach ($potentialPaths as $checkPath) {
                try {
                    if (\Illuminate\Support\Facades\Storage::disk($diskName)->exists($checkPath)) {
                        $content = \Illuminate\Support\Facades\Storage::disk($diskName)->get($checkPath);
                        $mimeType = str_ends_with(strtolower($checkPath), '.pptx') 
                            ? 'application/vnd.openxmlformats-officedocument.presentationml.presentation' 
                            : 'application/pdf';

                        return response($content, 200, [
                            'Content-Type' => $mimeType,
                            'Content-Length' => strlen($content),
                            'Access-Control-Allow-Origin' => '*',
                            'Cache-Control' => 'public, max-age=3600'
                        ]);
                    }
                } catch (\Exception $e) {}
            }
        }
    }

    // Bypass expired S3 pre-signed URLs by fetching directly via S3 disk
    if (str_contains($url, 'amazonaws.com')) {
        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'] ?? '';
        $path = ltrim($path, '/');
        
        try {
            if (\Illuminate\Support\Facades\Storage::disk('s3')->exists($path)) {
                $content = \Illuminate\Support\Facades\Storage::disk('s3')->get($path);
                $mimeType = str_ends_with(strtolower($path), '.pptx') 
                    ? 'application/vnd.openxmlformats-officedocument.presentationml.presentation' 
                    : 'application/pdf';

                return response($content, 200, [
                    'Content-Type' => $mimeType,
                    'Content-Length' => strlen($content),
                    'Access-Control-Allow-Origin' => '*',
                    'Cache-Control' => 'public, max-age=3600'
                ]);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("S3 Proxy Fetch Error: " . $e->getMessage());
        }
    }

    // Fix: URL may contain spaces or special characters which cURL doesn't handle well
    $encodedUrl = str_replace(' ', '%20', $url);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $encodedUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Referer: https://layosgroupllc.com'
    ]);
    
    $pdfContent = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200 || !$pdfContent) {
        return response()->json([
            'error' => 'Secure document stream interrupted.', 
            'status' => $httpCode,
            'details' => $curlError ?: 'Server returned non-200 status'
        ], 502);
    }
    return response($pdfContent, 200, [
        'Content-Type' => 'application/pdf', 
        'Access-Control-Allow-Origin' => '*',
        'Cache-Control' => 'public, max-age=3600'
    ]);
});


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return response()->json($request->user()->load(['cohorts' => function($q) {
            $q->wherePivotNotIn('status', ['dropped', 'inactive']);
        }, 'cohorts.course.modules.lessons', 'cohorts.instructor', 'completedLessons']));
    });
    
    Route::get('/my-enrollments', [StudentController::class, 'myEnrollments']);
    Route::get('/student/my-courses', [StudentController::class, 'myCourses']);
    Route::post('/lessons/{lesson}/complete', [StudentController::class, 'completeLesson']);
    Route::post('/upload-video', [CourseController::class, 'uploadVideo']);
    Route::post('/bunny/generate-signature', [CourseController::class, 'generateBunnyUploadSignature']);
    
    // RESTful Media Deletion
    Route::post('/remove-media-item', [CourseController::class, 'deleteAsset']);
    Route::post('/remove-file', [CourseController::class, 'deleteAsset']);
    
    Route::get('/instructor/dashboard-stats', [CohortController::class, 'dashboardStats']);
    Route::get('/instructor/activity-logs', [\App\Http\Controllers\ActivityLogController::class, 'index']);
    Route::post('/update-profile', [AuthController::class, 'updateProfile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::get('/active-sessions', [AuthController::class, 'getActiveSessions']);
    Route::delete('/active-sessions/{id}', [AuthController::class, 'logoutSession']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Assignments
    Route::get('/instructor/assignments', [AssignmentController::class, 'index']);
    Route::post('/instructor/assignments', [AssignmentController::class, 'store']);
    Route::delete('/instructor/assignments/{id}', [AssignmentController::class, 'destroy']);
    Route::get('/instructor/assignments/{id}/submissions', [AssignmentController::class, 'submissions']);
    Route::get('/student/assignments', [AssignmentController::class, 'studentAssignments']);
    Route::post('/student/assignments/{id}/submit', [AssignmentController::class, 'submit']);
    Route::get('/assignments/{id}/download', [AssignmentController::class, 'download']);

    // Interviews
    Route::get('/instructor/interviews', [\App\Http\Controllers\InterviewController::class, 'index']);
    Route::get('/instructor/interviews/{id}', [\App\Http\Controllers\InterviewController::class, 'show']);
    Route::post('/instructor/interviews', [\App\Http\Controllers\InterviewController::class, 'store']);
    Route::post('/instructor/interviews/{id}', [\App\Http\Controllers\InterviewController::class, 'update']); // Use POST for multipart/form-data updates
    Route::delete('/instructor/interviews/{id}', [\App\Http\Controllers\InterviewController::class, 'destroy']);
    Route::get('/student/interviews', [\App\Http\Controllers\InterviewController::class, 'studentInterviews']);

    Route::apiResource('live-sessions', \App\Http\Controllers\LiveSessionController::class);
    Route::get('/student/live-sessions', [\App\Http\Controllers\LiveSessionController::class, 'studentSessions']);
    Route::get('/courses/{course}/channels', [\App\Http\Controllers\ChannelMessageController::class, 'index']);
    Route::post('/courses/{course}/channels', [\App\Http\Controllers\ChannelMessageController::class, 'store']);
 
    // Global Channel
    Route::get('/general/channels', [\App\Http\Controllers\GeneralChannelController::class, 'index']);
    Route::post('/general/channels', [\App\Http\Controllers\GeneralChannelController::class, 'store']);
    
    // Direct Messaging
    Route::get('/direct-messages/contacts', [\App\Http\Controllers\DirectMessageController::class, 'contacts']);
    Route::get('/direct-messages/search', [\App\Http\Controllers\DirectMessageController::class, 'searchUsers']);
    Route::get('/direct-messages/{userId}', [\App\Http\Controllers\DirectMessageController::class, 'index']);
    Route::post('/direct-messages/{userId}', [\App\Http\Controllers\DirectMessageController::class, 'store']);

    // Course Channels
    Route::get('/course-channels/{courseId?}', [\App\Http\Controllers\CourseChannelController::class, 'index']);
    Route::post('/course-channels/{courseId?}', [\App\Http\Controllers\CourseChannelController::class, 'store']);
    Route::delete('/course-channels/{id}', [\App\Http\Controllers\CourseChannelController::class, 'destroy']);

    // Channel Messages
    Route::get('/channels/{channelId}/messages', [\App\Http\Controllers\ChannelMessageController::class, 'indexByChannel']);
    Route::post('/channels/{channelId}/messages', [\App\Http\Controllers\ChannelMessageController::class, 'storeByChannel']);
    Route::put('/channel-messages/{messageId}', [\App\Http\Controllers\ChannelMessageController::class, 'update']);
    Route::delete('/channel-messages/{messageId}', [\App\Http\Controllers\ChannelMessageController::class, 'destroy']);
    Route::delete('/direct-messages/{userId}/{messageId}', [\App\Http\Controllers\DirectMessageController::class, 'destroy']);

    // Instructor specific Course Management
    Route::post('/courses', [CourseController::class, 'store']);
    Route::put('/courses/{course}', [CourseController::class, 'update']);
    Route::delete('/courses/{course}', [CourseController::class, 'destroy']);
    
    Route::apiResource('cohorts', CohortController::class);

    // Certificates Management
    Route::get('/certificates', [CertificateController::class, 'index']);
    Route::post('/certificates/claim/{courseId}', [CertificateController::class, 'claim']);

    Route::get('/instructor/courses/{courseId}/certificate-template', [CertificateTemplateController::class, 'index']);
    Route::post('/instructor/courses/{courseId}/certificate-template', [CertificateTemplateController::class, 'store']);
    Route::delete('/instructor/courses/{courseId}/certificate-template', [CertificateTemplateController::class, 'destroy']);
    Route::put('/instructor/courses/{courseId}/certificate-template/positions', [CertificateTemplateController::class, 'updatePositions']);
    Route::post('/instructor/certificates/generate-manual', [CertificateController::class, 'generateManual']);
    Route::get('/course-videos', [CourseController::class, 'listVideos']);
});

// Open Public Access Routes
Route::get('/courses', [CourseController::class, 'index']);
Route::get('/courses/{course}', [CourseController::class, 'show']);
Route::get('/fix-storage-link', [CourseController::class, 'fixStorageLink']);
Route::get('/instructor/students', [StudentController::class, 'index']);
Route::get('/instructor/students/{student}', [StudentController::class, 'show']);
Route::put('cohorts/{cohort}/students/{user}', [CohortController::class, 'updateStudentPivot']);
Route::post('/instructor/students/{student}/assign-cohorts', [StudentController::class, 'assignCohorts']);
Route::apiResource('students', StudentController::class);
