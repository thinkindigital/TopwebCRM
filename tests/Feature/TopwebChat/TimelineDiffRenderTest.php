<?php

it('renders the timeline with a keyed diff and a full-rebuild fallback', function () {
    $view = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/show.blade.php')
    );

    expect($view)->toContain(
        'renderTimelineDiff',
        'renderTimelineFull',
        'buildMessageArticle',
        'dataset.renderSig',
        'topweb-chat-date-separator',
        'data-message-id'
    );
});
