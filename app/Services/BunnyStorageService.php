<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BunnyStorageService
{
    protected $storageZone;
    protected $accessKey;
    protected $region;
    protected $pullZone;

    public function __construct()
    {
        $this->storageZone = config('services.bunny.storage_zone');
        $this->accessKey = config('services.bunny.access_key');
        $this->region = config('services.bunny.region', 'storage');
        $this->pullZone = config('services.bunny.pull_zone');
    }

    /**
     * Upload a file to Bunny.net Storage
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $path
     * @return string|null The public URL of the uploaded file
     */
    public function upload($file, $path = '')
    {
        try {
            $fileName = str_replace(' ', '_', $file->getClientOriginalName());
            $uniqueName = time() . '_' . $fileName;
            $fullPath = trim($path, '/') . '/' . $uniqueName;
            
            // Bunny Storage API uses PUT request
            // Endpoint: https://{region}.bunnycdn.com/{storageZone}/{path}/{fileName}
            $baseUrl = $this->region === 'storage' 
                ? "https://storage.bunnycdn.com" 
                : "https://{$this->region}.storage.bunnycdn.com";

            $response = Http::withHeaders([
                'AccessKey' => $this->accessKey,
                'Content-Type' => 'application/octet-stream',
            ])->withBody(fopen($file->getRealPath(), 'r'), $file->getMimeType())
              ->put("{$baseUrl}/{$this->storageZone}/{$fullPath}");

            if ($response->successful()) {
                return "https://{$this->pullZone}/" . ltrim($fullPath, '/');
            }

            $errorMsg = "Bunny Storage Upload Failed (HTTP {$response->status()}): " . $response->body();
            Log::error($errorMsg);
            throw new \Exception($errorMsg);
        } catch (\Exception $e) {
            Log::error("Bunny Storage Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Upload raw content to Bunny.net Storage
     *
     * @param string $content
     * @param string $fileName
     * @param string $path
     * @param string $mimeType
     * @return string|null The public URL
     */
    public function uploadContent($content, $fileName, $path = '', $mimeType = 'application/octet-stream')
    {
        try {
            $safeFileName = str_replace(' ', '_', $fileName);
            $uniqueName = time() . '_' . $safeFileName;
            $fullPath = trim($path, '/') . '/' . $uniqueName;
            
            $baseUrl = $this->region === 'storage' 
                ? "https://storage.bunnycdn.com" 
                : "https://{$this->region}.storage.bunnycdn.com";

            $response = Http::withHeaders([
                'AccessKey' => $this->accessKey,
                'Content-Type' => $mimeType,
            ])->withBody($content, $mimeType)
              ->put("{$baseUrl}/{$this->storageZone}/{$fullPath}");

            if ($response->successful()) {
                return "https://{$this->pullZone}/" . ltrim($fullPath, '/');
            }

            Log::error("Bunny Storage Upload Content Failed: " . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error("Bunny Storage Content Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Delete a file from Bunny.net Storage
     *
     * @param string $url The full URL of the file
     * @return bool
     */
    public function delete($url)
    {
        try {
            // Extract the path from the URL
            // URL format: https://{pullZone}/{path}
            $path = str_replace("https://{$this->pullZone}/", '', $url);
            
            $baseUrl = $this->region === 'storage' 
                ? "https://storage.bunnycdn.com" 
                : "https://{$this->region}.storage.bunnycdn.com";

            $response = Http::withHeaders([
                'AccessKey' => $this->accessKey,
            ])->delete("{$baseUrl}/{$this->storageZone}/{$path}");

            return $response->successful();
        } catch (\Exception $e) {
            Log::error("Bunny Storage Delete Error: " . $e->getMessage());
            return false;
        }
    }
}
