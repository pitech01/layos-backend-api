<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$templates = \DB::table('certificate_templates')->whereNotNull('layout_json')->get();
foreach ($templates as $t) {
    echo "ID: " . $t->id . "\n";
    echo "COurse ID: " . $t->course_id . "\n";
    echo "JSON: " . $t->layout_json . "\n";
}
