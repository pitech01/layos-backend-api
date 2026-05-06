<?php

namespace App\Http\Controllers;

use App\Models\CertificateTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class CertificateTemplateController extends Controller
{
    public function index($courseId)
    {
        $template = CertificateTemplate::where('course_id', $courseId)->first();
        return response()->json($template);
    }

    public function store(Request $request, $courseId)
    {
        $request->validate([
            'template' => 'required|image|mimes:jpeg,jpg',
        ]);

        $file = $request->file('template');
        
        if (!extension_loaded('gd')) {
            return response()->json(['error' => 'GD extension not loaded'], 500);
        }

        try {
            // Resize image but maintain aspect ratio
            $manager = ImageManager::gd();
            $image = $manager->read($file);
            $image->scale(height: 1414); 
            $encoded = $image->toJpeg(90);

            $bunnyService = new \App\Services\BunnyStorageService();
            $path = $bunnyService->uploadContent($encoded->toString(), $file->hashName(), 'certificates/templates', 'image/jpeg');
            
            if (!$path) {
                throw new \Exception("Upload to Bunny.net failed.");
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        $template = CertificateTemplate::updateOrCreate(
            ['course_id' => $courseId],
            [
                'template_path' => $path,
                'storage_provider' => 'bunny',
                'name_x' => 50, 'name_y' => 45,
                'course_x' => 50, 'course_y' => 60,
                'date_x' => 80, 'date_y' => 85,
                'cert_id_x' => 20, 'cert_id_y' => 85,
                'qr_x' => 50, 'qr_y' => 85,
                'qr_size' => 120,
                'font_color' => '#000000',
                'font_size' => 36
            ]
        );

        return response()->json($template, 201);
    }




    public function destroy($courseId)
    {
        $template = CertificateTemplate::where('course_id', $courseId)->firstOrFail();
        
        $disk = env('FILESYSTEM_DISK', 'public');
        if (Storage::disk($disk)->exists($template->template_path)) {
            Storage::disk($disk)->delete($template->template_path);
        }
        
        $template->delete();

        return response()->json(['message' => 'Template deleted successfully']);
    }

    public function updatePositions(Request $request, $courseId)
    {
        $template = CertificateTemplate::updateOrCreate(
            ['course_id' => $courseId],
            $request->only([
                'name_x', 'name_y', 'course_x', 'course_y', 'date_x', 'date_y',
                'cert_id_x', 'cert_id_y', 'qr_x', 'qr_y', 'qr_size',
                'font_color', 'font_size',
                'bg_x', 'bg_y', 'bg_width', 'bg_height', 'bg_object_fit',
                'layout_json'
            ])
        );

        return response()->json($template);
    }
}
