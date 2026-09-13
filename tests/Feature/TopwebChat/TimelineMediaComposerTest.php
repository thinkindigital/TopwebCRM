<?php

it('keeps the media composer affordances in the timeline view', function () {
    $composer = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/partials/composer.blade.php')
    );
    $runtime = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/partials/chat-runtime.blade.php')
    );

    expect($composer)->toContain(
        'id="topweb-chat-attach-image"',
        'id="topweb-chat-media-input"',
        'id="topweb-chat-attach-document"',
        'id="topweb-chat-document-input"',
        'id="topweb-chat-media-preview"',
        'id="topweb-chat-content"'
    );
    expect($runtime)->toContain(
        'clearMediaPreview',
        "reportClientEvent('error', 'client.send_failed'",
    );
});
