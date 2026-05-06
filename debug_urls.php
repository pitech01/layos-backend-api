<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Total Lessons: " . \App\Models\Lesson::count() . "\n";
echo "Lessons with Video: " . \App\Models\Lesson::whereNotNull('video_url')->count() . "\n";

$lessons = \App\Models\Lesson::whereNotNull('video_url')->get();
foreach ($lessons as $lesson) {
    echo "ID: {$lesson->id} | Raw: " . $lesson->getRawOriginal('video_url') . " | Clean: {$lesson->video_url}\n";
}
