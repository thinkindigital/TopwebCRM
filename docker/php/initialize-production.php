<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

require '/var/www/html/vendor/autoload.php';

$app = require '/var/www/html/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$name = trim((string) getenv('ADMIN_NAME'));
$email = trim((string) getenv('ADMIN_EMAIL'));
$password = (string) getenv('ADMIN_PASSWORD');

if ($name === '' || $email === '' || $password === '') {
    fwrite(STDERR, "ADMIN_NAME, ADMIN_EMAIL and ADMIN_PASSWORD are required for the initial install.\n");
    exit(1);
}

if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "ADMIN_EMAIL is invalid.\n");
    exit(1);
}

DB::table('users')->where('id', 1)->update([
    'name' => $name,
    'email' => $email,
    'password' => Hash::make($password),
    'status' => 1,
    'updated_at' => now(),
]);

File::put(storage_path('installed'), 'TopwebCRM is successfully installed');
