<?php

// E-03: fragmento do servidor é a representação canônica; JS não reconstrói markup.

it('renders the timeline from a single server fragment', function () {
    $workspace = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/partials/workspace.blade.php')
    );
    $runtime = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/partials/chat-runtime.blade.php')
    );
    $partial = base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/partials/timeline-messages.blade.php');

    expect(file_exists($partial))->toBeTrue();
    expect($workspace)->toContain('conversations.partials.timeline-messages');
    expect($runtime)->toContain(
        'renderFragment',
        "fragment', 'timeline'",
        'topweb-chat-poll-meta'
    );
    expect($workspace.$runtime)->not->toContain('renderTimelineDiff')
        ->and($workspace.$runtime)->not->toContain('buildMessageArticle');
});
