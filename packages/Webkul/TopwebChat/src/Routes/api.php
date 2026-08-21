<?php

use Illuminate\Support\Facades\Route;
use Webkul\TopwebChat\Http\Controllers\WebhookController;

Route::get('health', fn () => response()->json([
    'module' => 'topweb-chat',
    'status' => 'ok',
]))->name('api.topweb_chat.health');

Route::post('webhooks/openwa/{instance}', [WebhookController::class, 'store'])
    ->name('api.topweb_chat.webhooks.openwa');
