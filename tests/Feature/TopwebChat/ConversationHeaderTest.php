<?php

// E-02 C1: cabeçalho da conversa extraído em partial sem mudar comportamento.

it('renders the conversation header through a dedicated partial', function () {
    $show = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/show.blade.php')
    );

    expect($show)->toContain('conversations.partials.conversation-header');
});

it('keeps header markup in a single place', function () {
    $partial = base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/partials/conversation-header.blade.php');
    $show = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/show.blade.php')
    );

    expect(file_exists($partial))->toBeTrue();
    $markup = file_get_contents($partial);
    expect($markup)->toContain('topweb-chat-sync-status')
        ->and($markup)->toContain('topweb-chat-connection-badge')
        ->and($markup)->toContain('topweb-chat-instance-status');

    // O monólito não duplica o bloco extraído.
    expect($show)->not->toContain('topweb-chat-connection-badge');
});
