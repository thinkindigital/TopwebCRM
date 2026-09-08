<?php

it('pins the timeline with a sentinel anchor and a canonicalized locale', function () {
    $view = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/show.blade.php')
    );

    expect($view)->toContain(
        'id="topweb-chat-anchor"',
        'IntersectionObserver',
        'getCanonicalLocales',
        "replaceAll('_', '-')",
        'isPinned',
        "reportClientEvent('error', 'client.refresh_failed'"
    )->and($view)->not->toContain(
        "replace('_', '-')"
    );
});
