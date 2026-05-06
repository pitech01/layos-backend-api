<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BunnyStreamService
{
    protected $libraryId;
    protected $apiKey;

    public function __construct()
    {
        $this->libraryId = config('services.bunny.stream_library_id');
        $this->apiKey = config('services.bunny.stream_api_key');
    }

    /**
     * Create a video placeholder in Bunny Stream
     *
     * @param string $title
     * @return array|null [videoId, libraryId, signature, expiration]
     */
    public function prepareDirectUpload($title)
    {
        try {
            // 1. Create video object
            $response = Http::withHeaders([
                'AccessKey' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("https://video.bunnycdn.com/library/{$this->libraryId}/videos", [
                'title' => $title,
            ]);

            if (!$response->successful()) {
                throw new \Exception("Failed to create video placeholder: " . $response->body());
            }

            $videoId = $response->json()['guid'];
            $expiration = time() + 3600; // 1 hour
            
            // Generate signature: sha256(library_id + api_key + expiration + video_id)
            $signature = hash('sha256', $this->libraryId . $this->apiKey . $expiration . $videoId);

            Log::info("Bunny Direct Upload Prepared", [
                'libraryId' => $this->libraryId,
                'videoId' => $videoId,
                'expiration' => $expiration,
                // Log first 4 and last 4 of signature for verification
                'signature_hint' => substr($signature, 0, 4) . '...' . substr($signature, -4)
            ]);

            return [
                'videoId' => $videoId,
                'libraryId' => $this->libraryId,
                'signature' => $signature,
                'expiration' => $expiration
            ];
        } catch (\Exception $e) {
            Log::error("Bunny Prepare Upload Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Upload a video to Bunny Stream (Server-side)
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $title
     * @return string|null The playback URL or Video ID
     */
    public function upload($file, $title = '')
    {
        try {
            $title = $title ?: $file->getClientOriginalName();

            // 1. Create video object in Bunny Stream
            $prep = $this->prepareDirectUpload($title);
            if (!$prep) throw new \Exception("Failed to prepare upload.");

            $videoId = $prep['videoId'];

            // 2. Upload the video file
            $uploadResponse = Http::withHeaders([
                'AccessKey' => $this->apiKey,
                'Content-Type' => 'application/octet-stream',
            ])->withBody(fopen($file->getRealPath(), 'r'), $file->getMimeType())
              ->put("https://video.bunnycdn.com/library/{$this->libraryId}/videos/{$videoId}");

            if ($uploadResponse->successful()) {
                return "https://iframe.mediadelivery.net/play/{$this->libraryId}/{$videoId}";
            }

            $errorMsg = "Bunny Stream Upload Failed (HTTP {$uploadResponse->status()}): " . $uploadResponse->body();
            Log::error($errorMsg);
            throw new \Exception($errorMsg);
        } catch (\Exception $e) {
            Log::error("Bunny Stream Error: " . $e->getMessage());
            throw $e;
        }
    }
    /**
     * List videos from Bunny Stream library
     *
     * @param int $page
     * @param int $itemsPerPage
     * @return array
     */
    public function listVideos($page = 1, $itemsPerPage = 100)
    {
        try {
            $response = Http::withHeaders([
                'AccessKey' => $this->apiKey,
                'Accept' => 'application/json',
            ])->get("https://video.bunnycdn.com/library/{$this->libraryId}/videos", [
                'page' => $page,
                'itemsPerPage' => $itemsPerPage,
                'orderBy' => 'date',
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error("Bunny Stream List Failed: " . $response->body());
            return ['items' => []];
        } catch (\Exception $e) {
            Log::error("Bunny Stream List Error: " . $e->getMessage());
            return ['items' => []];
        }
    }
}
