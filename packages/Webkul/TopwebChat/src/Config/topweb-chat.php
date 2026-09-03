<?php

return [
    'provider' => env('TOPWEB_CHAT_PROVIDER', 'openwa'),
    'base_url' => env('TOPWEB_CHAT_BASE_URL', 'http://openwa:2785'),
    'public_url' => env('TOPWEB_CHAT_PUBLIC_URL'),
    'timeout' => (int) env('TOPWEB_CHAT_REQUEST_TIMEOUT', 30),
    'connect_timeout' => (int) env('TOPWEB_CHAT_CONNECT_TIMEOUT', 10),
    'history_batch_size' => (int) env('TOPWEB_CHAT_HISTORY_BATCH_SIZE', 20),

    'send_max_attempts' => (int) env('TOPWEB_CHAT_SEND_MAX_ATTEMPTS', 5),

    'attendance' => [
        'inactivity_minutes' => (int) env(
            'TOPWEB_CHAT_ATTENDANCE_INACTIVITY_MINUTES',
            1440
        ),
        'close_batch_size' => (int) env(
            'TOPWEB_CHAT_ATTENDANCE_CLOSE_BATCH_SIZE',
            100
        ),
    ],

    'openwa' => [
        'default_session_name' => env('TOPWEB_CHAT_OPENWA_SESSION', 'topweb'),
        'auto_start_session' => env('TOPWEB_CHAT_OPENWA_AUTO_START', true),
        'webhook_events' => [
            'message.received',
            'message.sent',
            'message.ack',
            'message.failed',
            'message.revoked',
            'message.reaction',
            'message.edited',
            'session.status',
            'session.authenticated',
            'session.disconnected',
            'session.reconnect_loop',
            'session.restriction',
            'group.join',
            'group.leave',
            'group.update',
            'group.join_request',
            'call.received',
            'call.accepted',
            'call.rejected',
            'call.missed',
            'status.received',
        ],
        'webhook_retry_count' => (int) env('TOPWEB_CHAT_WEBHOOK_RETRY_COUNT', 3),
        'webhook_timeout' => (int) env('TOPWEB_CHAT_WEBHOOK_TIMEOUT', 10),
        'media_inline_max_bytes' => (int) env('TOPWEB_CHAT_MEDIA_INLINE_MAX_BYTES', 1048576), // 1MiB
        'media_max_bytes' => (int) env('TOPWEB_CHAT_MEDIA_MAX_BYTES', 52428800), // 50MiB
    ],
];
