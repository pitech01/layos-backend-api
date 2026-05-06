<?php
$file = __DIR__ . '/storage/live_recordings/2cd2184d-9744-44fb-a00d-9484743e73fc.mp4';
echo "Checking: " . $file . "\n";
if (file_exists($file)) {
    echo "Exists: YES\n";
    if (is_readable($file)) {
        echo "Readable: YES\n";
    } else {
        echo "Readable: NO\n";
    }
} else {
    echo "Exists: NO\n";
}
