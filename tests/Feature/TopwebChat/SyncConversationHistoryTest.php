<?php

use Webkul\TopwebChat\Jobs\SyncConversationHistory;

it('accepts OpenWA Unix timestamps while restoring conversation history', function () {
    $job = new SyncConversationHistory(1);
    $method = new ReflectionMethod($job, 'parseTimestamp');
    $method->setAccessible(true);

    $timestamp = $method->invoke($job, 1719312000);

    expect($timestamp->getTimestamp())->toBe(1719312000);
});
