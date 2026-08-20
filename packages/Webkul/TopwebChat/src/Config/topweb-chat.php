<?php

return [
    'provider' => 'ryzeapi',
    'base_url' => env('TOPWEB_CHAT_BASE_URL', 'https://ryzeapi.cloud'),
    'public_url' => env('TOPWEB_CHAT_PUBLIC_URL'),
    'timeout' => (int) env('TOPWEB_CHAT_REQUEST_TIMEOUT', 30),
    'connect_timeout' => (int) env('TOPWEB_CHAT_CONNECT_TIMEOUT', 10),
    'history_batch_size' => (int) env('TOPWEB_CHAT_HISTORY_BATCH_SIZE', 20),

    'send_max_attempts' => (int) env('TOPWEB_CHAT_SEND_MAX_ATTEMPTS', 5),
];
