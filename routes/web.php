<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect(config('app.admin_path').'/login');
});
