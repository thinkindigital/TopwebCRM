<?php

// E-02 C3: composer extraído em partial sem mudar comportamento.

it('renders the composer through a dedicated partial', function () {
    $workspace = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/partials/workspace.blade.php')
    );
    $partial = base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/partials/composer.blade.php');

    expect(file_exists($partial))->toBeTrue();
    $markup = file_get_contents($partial);
    expect($markup)->toContain('topweb-chat-send-form')
        ->and($markup)->toContain('topweb-chat-attach-menu')
        ->and($markup)->toContain('name="operation_key"')
        ->and($markup)->toContain('data-batch-url')
        ->and($markup)->toContain('multiple')
        ->and($markup)->toContain('audio/*');

    $runtime = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/partials/chat-runtime.blade.php')
    );

    expect($runtime)->toContain('pendingAttachments')
        ->and($runtime)->toContain('trayRemove')
        ->and($runtime)->toContain('dataset.batchUrl');

    expect($workspace)->toContain('conversations.partials.composer');
    expect($workspace)->not->toContain('id="topweb-chat-send-form"');
});
