<?php

it('forwards the browser locale in client events', function () {
    $controller = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Http/Controllers/ConversationController.php')
    );
    $view = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/show.blade.php')
    );

    expect($controller)->toContain("'browser_locale'")
        ->and($view)->toContain('browser_locale');
});
