<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

use App\Models\Cohort;
use App\Models\User;

try {
    foreach (Cohort::all() as $c) {
        echo "Cohort ID: " . $c->id . " | Name: " . $c->name . " | Instructor ID: " . $c->instructor_id . "\n";
    }
    foreach (User::all() as $u) {
        echo "User ID: " . $u->id . " | Name: " . $u->name . " | Role: " . $u->role . " | Email: " . $u->email . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
