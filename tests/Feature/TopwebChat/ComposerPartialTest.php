<?php

// E-02 C3: composer extraído em partial sem mudar comportamento.

it('renders the composer through a dedicated partial', function () {
    $show = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/show.blade.php')
    );
    $partial = base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/partials/composer.blade.php');

    expect(file_exists($partial))->toBeTrue();
    $markup = file_get_contents($partial);
    expect($markup)->toContain('topweb-chat-send-form')
        ->and($markup)->toContain('topweb-chat-attach-menu')
        ->and($markup)->toContain('name="operation_key"');

    expect($show)->toContain('conversations.partials.composer');
    expect($show)->not->toContain('id="topweb-chat-send-form"');
});
