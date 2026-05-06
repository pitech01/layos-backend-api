<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

config(['filesystems.disks.s3.throw' => true]);

try {
    $fileName = 'course_videos/test.mp4';
    echo "URL: " . \Illuminate\Support\Facades\Storage::disk('s3')->url($fileName) . "\n";
    echo "Temporary URL: " . \Illuminate\Support\Facades\Storage::disk('s3')->temporaryUrl($fileName, now()->addMinutes(15)) . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
