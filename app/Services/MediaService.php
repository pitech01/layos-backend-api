<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class MediaService
{
    /**
     * Get the full URL for a media file based on its storage provider.
     *
     * @param mixed $entity The model instance (Lesson, Course, etc.)
     * @param string $field The field name containing the URL/path (video_url, file_url, thumbnail, etc.)
     * @param string|null $rawValue The already fetched raw value to avoid recursion
     * @return string|null
     */
    public static function getMediaUrl($entity, $field = 'file_url', $rawValue = null)
    {
        $value = $rawValue ?? (method_exists($entity, 'getRawOriginal') ? $entity->getRawOriginal($field) : $entity->$field);
        
        if (!$value) {
            return null;
        }

        // 1. If it's already a full URL (Bunny, external, etc.), return it immediately
        // (unless it's an S3 URL that needs signing, which we handle below)
        if (str_starts_with($value, 'http') && !str_contains($value, 's3')) {
            // Fix legacy localhost URLs
            if (str_contains($value, 'localhost:8000') || str_contains($value, '127.0.0.1:8000')) {
                $productionUrl = 'https://layosgroupllc.com/backend';
                $value = str_replace(['http://localhost:8000', 'http://127.0.0.1:8000'], $productionUrl, $value);
            }
            
            // Force HTTPS for production assets
            if (str_contains($value, 'layosgroupllc.com')) {
                $value = str_replace('http://', 'https://', $value);
            }
            
            return $value;
        }

        $provider = $entity->storage_provider ?? config('filesystems.default');

        switch ($provider) {
            case 'bunny':
                return $value;

            case 's3':
            case 'aws':
                // Handle S3 temporary URLs
                try {
                    $path = $value;
                    if (str_contains($value, 'http')) {
                        $path = ltrim(parse_url($value, PHP_URL_PATH), '/');
                        // Strip bucket name if it's in the path (S3 legacy)
                        $bucket = config('filesystems.disks.s3.bucket');
                        if ($bucket && str_starts_with($path, $bucket . '/')) {
                            $path = substr($path, strlen($bucket) + 1);
                        }
                    }
                    return Storage::disk('s3')->temporaryUrl($path, now()->addHours(12));
                } catch (\Throwable $e) {
                    return $value;
                }

            case 'local':
            default:
                // If it's already a full URL, handle legacy replacements
                if (str_starts_with($value, 'http')) {
                    if (str_contains($value, 'localhost:8000') || str_contains($value, '127.0.0.1:8000')) {
                        $productionUrl = 'https://layosgroupllc.com/backend';
                        $value = str_replace(['http://localhost:8000', 'http://127.0.0.1:8000'], $productionUrl, $value);
                    }
                    
                    // Force HTTPS for production assets
                    if (str_contains($value, 'layosgroupllc.com')) {
                        $value = str_replace('http://', 'https://', $value);
                    }
                    
                    return $value;
                }
                
                // Clean the path to prevent double 'storage/' or internal Laravel prefixes
                $cleanPath = str_replace(['/storage/', 'storage/', 'app/public/', '/app/public/'], '', $value);
                $cleanPath = ltrim($cleanPath, '/');
                
                // Otherwise, assume it's a path in the storage/public directory
                try {
                    $url = Storage::disk('public')->url($cleanPath);
                } catch (\Exception $e) {
                    $url = asset('storage/' . $cleanPath);
                }
                
                // Safety fix for production if asset() or Storage::url() still points to localhost
                if (str_contains($url, 'localhost')) {
                    $url = str_replace(['http://localhost:8000', 'http://127.0.0.1:8000'], 'https://layosgroupllc.com/backend', $url);
                }

                return str_replace('http://', 'https://', $url);
        }
    }
}
