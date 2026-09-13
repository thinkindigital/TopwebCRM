<?php

it('forwards the browser locale with client events', function () {
    $controller = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Http/Controllers/ConversationController.php')
    );
    $runtime = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/partials/chat-runtime.blade.php')
    );

    expect($controller)->toContain("'browser_locale'")
        ->and($runtime)->toContain('browser_locale');
});
