<?php

it('keeps the media composer affordances in the timeline view', function () {
    $view = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/show.blade.php')
    );

    expect($view)->toContain(
        'id="topweb-chat-attach"',
        'id="topweb-chat-media-input"',
        'name="media"',
        'id="topweb-chat-media-preview"',
        'id="topweb-chat-content"',
        'clearMediaPreview',
        "reportClientEvent('error', 'client.send_failed'",
    );
});
