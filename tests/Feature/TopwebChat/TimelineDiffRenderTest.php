<?php

// E-03: fragmento do servidor é a representação canônica; JS não reconstrói markup.

it('renders the timeline from a single server fragment', function () {
    $show = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/show.blade.php')
    );
    $partial = base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/partials/timeline-messages.blade.php');

    expect(file_exists($partial))->toBeTrue();
    expect($show)->toContain('conversations.partials.timeline-messages')
        ->and($show)->toContain('renderFragment')
        ->and($show)->toContain("fragment', 'timeline'")
        ->and($show)->not->toContain('renderTimelineDiff')
        ->and($show)->not->toContain('renderTimelineFull')
        ->and($show)->not->toContain('buildMessageArticle')
        ->and($show)->not->toContain('dataset.renderSig');
});
