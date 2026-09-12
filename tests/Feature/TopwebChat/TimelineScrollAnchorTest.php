<?php

it('pins the timeline with a sentinel anchor and a canonicalized locale', function () {
    $view = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/show.blade.php')
    );
    $partial = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/partials/timeline-messages.blade.php')
    );

    expect($partial)->toContain('id="topweb-chat-anchor"');
    expect($view)->toContain(
        'IntersectionObserver',
        'getCanonicalLocales',
        "replaceAll('_', '-')",
        'isPinned',
        "reportClientEvent('error', 'client.refresh_failed'"
    )->and($view)->not->toContain(
        "replace('_', '-')"
    );
});
