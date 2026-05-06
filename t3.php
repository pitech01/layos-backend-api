<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$users = \DB::table('students')->get();
foreach ($users as $u) {
    echo "ID: " . $u->id . " Name Type: " . gettype($u->name) . " Value: " . $u->name . "\n";
}
