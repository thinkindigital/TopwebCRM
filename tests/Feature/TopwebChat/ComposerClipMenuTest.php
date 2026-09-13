<?php

// V-03: composer com clip menu SVG único, sem emoji funcional, sem affordance morta.

it('uses a single svg clip menu without emoji buttons or dead contact action', function () {
    $composer = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/partials/composer.blade.php')
    );

    expect($composer)->toContain('id="topweb-chat-attach-menu"')
        ->and($composer)->toContain('<svg')
        ->and($composer)->not->toContain('id="topweb-chat-attach-contact"')
        ->and($composer)->not->toContain('name="contact"')
        ->and($composer)->not->toContain('>📷<')
        ->and($composer)->not->toContain('>📄<')
        ->and($composer)->not->toContain('>📍<')
        ->and($composer)->not->toContain('>👤<');
});

it('keeps the working composer contracts untouched', function () {
    $composer = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/partials/composer.blade.php')
    );

    expect($composer)->toContain(
        'id="topweb-chat-attach-image"',
        'id="topweb-chat-media-input"',
        'id="topweb-chat-attach-document"',
        'id="topweb-chat-document-input"',
        'id="topweb-chat-attach-location"',
        'id="topweb-chat-location-input"',
        'id="topweb-chat-media-preview"',
        'id="topweb-chat-content"',
        'name="operation_key"'
    );
});
