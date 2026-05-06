<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
// Using FQCN for Chillerlan to avoid potential conflicts with global aliases

class CertificateController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if ($user->role === 'student') {
            $certificates = Certificate::where('user_id', $user->id)
                ->with('course')
                ->latest()
                ->get();
        } else {
            $certificates = Certificate::with(['user', 'course'])->latest()->get();
        }
        return response()->json($certificates);
    }

    public function verify($uuid)
    {
        $uuid = strtoupper($uuid);
        $certificate = Certificate::where('certificate_uuid', $uuid)->firstOrFail();
        return response()->json($certificate);
    }

    public function download($uuid)
    {
        $certificate = Certificate::where('certificate_uuid', $uuid)->firstOrFail();
        
        // Use the accessor-generated URL for redirection to download, 
        // or fetch and stream if it's secure. For simplicity, redirect to the URL.
        return redirect($certificate->certificate_path);
    }

    /**
     *  Real generation logic using Intervention Image v3
     */
    public function generateManual(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'full_name' => 'required|string',
            'course_title' => 'required|string',
            'issued_at' => 'required|date',
        ]);

        $template = CertificateTemplate::where('course_id', $request->course_id)->first();
        if (!$template) {
            return response()->json(['message' => 'No template found for this course.'], 404);
        }

        $uuid = $request->certificate_uuid ?? strtoupper(Str::random(8)); // Preserve or generate new code
        // NEW: Force short code if current one is too long (legacy UUIDs)
        if (strlen($uuid) > 15) {
            $uuid = strtoupper(Str::random(8));
        }

        // 1. Generate QR Code
        $bunnyService = new \App\Services\BunnyStorageService();
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        $verifyUrl = $frontendUrl . '/verify/' . $uuid;
        
        $qrOptions = new \chillerlan\QRCode\QROptions([
            'outputInterface' => \chillerlan\QRCode\Output\QRGdImagePNG::class,
            'eccLevel'   => \chillerlan\QRCode\Common\EccLevel::L,
            'scale'      => 10,
        ]);
        
        $qrData = (new \chillerlan\QRCode\QRCode($qrOptions))->render($verifyUrl);
        $qrPath = $bunnyService->uploadContent($qrData, $uuid . '.png', 'certificates/qr', 'image/png');

        // 2. Load Template Image - use the accessor URL
        try {
            $templateUrl = $template->template_path;
            $templateRaw = file_get_contents($templateUrl);
            
            $manager = \Intervention\Image\ImageManager::gd();
            $img = $manager->read($templateRaw);
            $w = $img->width();
            $h = $img->height();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Template background file could not be loaded: ' . $e->getMessage()], 404);
        }
        
        // Scale Factor (Designer used 1000px width)
        $scale = $w / 1000;

        // 3. Process Elements from layout_json
        $layout = $template->layout_json;
        if ($layout && isset($layout['elements'])) {
            foreach ($layout['elements'] as $el) {
                // Replace placeholders
                $text = $el['text'] ?? '';
                $text = str_replace(
                    ['{{full_name}}', '{{course_title}}', '{{date}}', '{{cert_id}}'], 
                    [$request->full_name, $request->course_title, $request->issued_at, $uuid], 
                    $text
                );

                $x = $el['x'] * $scale;
                $y = $el['y'] * $scale;
                $align = $el['align'] ?? 'left';

                if ($el['type'] === 'qr') {
                    // Overlay QR
                    $qrImg = $manager->read($qrData);
                    $qrImg->resize($el['width'] * $scale, $el['height'] * $scale);
                    $img->place($qrImg, 'top-left', $x, $y);
                } else {
                    // Render Text
                    if ($align === 'center' && isset($el['width'])) {
                        $x += ($el['width'] * $scale) / 2;
                    }

                    $img->text($text, $x, $y, function($font) use ($el, $scale, $align) {
                        $font->filename('C:/Windows/Fonts/arial.ttf'); 
                        $font->size($el['fontSize'] * $scale);
                        $font->color($el['fill'] ?? '#000000');
                        $font->align($align);
                        $font->valign('top');
                    });
                }
            }
        }

        // 4. Save Final Certificate
        $encoded = $img->toJpeg(90);
        $finalPath = $bunnyService->uploadContent($encoded->toString(), $uuid . '.jpg', 'certificates/issued', 'image/jpeg');

        $certificate = Certificate::create([
            'certificate_uuid' => $uuid,
            'user_id' => $request->user_id,
            'course_id' => $request->course_id,
            'full_name' => $request->full_name,
            'course_title' => $request->course_title,
            'certificate_path' => $finalPath,
            'qr_code_path' => $qrPath,
            'storage_provider' => 'bunny',
            'issued_by' => $request->issued_by ?? auth()->user()?->name ?? 'Instructor',
            'issued_at' => $request->issued_at,
        ]);

        $course = \App\Models\Course::find($request->course_id);
        if ($course && $request->user_id) {
            $student = \App\Models\User::find($request->user_id);
            if ($student) {
                \App\Models\ActivityLog::create([
                    'user_id' => $student->id,
                    'instructor_id' => $course->instructor_id,
                    'action' => 'certificate_claimed',
                    'description' => $student->name . ' claimed certificate for course: ' . $request->course_title,
                    'metadata' => ['certificate_id' => $certificate->id, 'course_id' => $request->course_id]
                ]);
            }
        }

        return response()->json($certificate, 201);
    }

    /**
     * Students "claim" their certificate once course is 100% complete
     */
    public function claim(Request $request, $courseId)
    {
        $user = $request->user();
        
        // 0. Verify completion
        $enrollment = $user->cohorts()
            ->where('course_id', $courseId)
            ->wherePivot('progress', '>=', 100)
            ->with(['course', 'instructor'])
            ->first();

        if (!$enrollment) {
            return response()->json(['message' => 'Course not completed yet (100% progress required).'], 400);
        }

        // 1. Check if already exists and delete to allow regeneration
        $existing = Certificate::where('user_id', $user->id)
            ->where('course_id', $courseId)
            ->first();
            
        if ($existing) {
            $request->merge(['certificate_uuid' => $existing->certificate_uuid]);
            $disk = env('FILESYSTEM_DISK', 'public');
            Storage::disk($disk)->delete([$existing->certificate_path, $existing->qr_code_path]);
            $existing->delete();
        }

        // 2. Load Template
        $template = CertificateTemplate::where('course_id', $courseId)->first();
        if (!$template) {
            return response()->json(['message' => 'Certificate template not configured for this course.'], 404);
        }

        // 3. Reuse generation logic (refactored or directly called)
        // For simplicity here, I'll bypass the manual request and use model data
        $request->merge([
            'course_id' => $courseId,
            'full_name' => $user->name,
            'course_title' => $enrollment->course->title,
            'issued_at' => Carbon::now()->toDateString(),
            'user_id' => $user->id,
            'issued_by' => $enrollment->instructor->name ?? 'Layos Group'
        ]);

        return $this->generateManual($request);
    }
}
