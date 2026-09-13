<?php

it('pins the timeline with a sentinel anchor and a canonicalized locale', function () {
    $runtime = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/partials/chat-runtime.blade.php')
    );

    expect($runtime)->toContain(
        'IntersectionObserver',
        'getCanonicalLocales',
        "replaceAll('_', '-')",
        'isPinned',
        "reportClientEvent('error', 'client.refresh_failed'"
    )->and($runtime)->not->toContain(
        "replace('_', '-')"
    );
});
