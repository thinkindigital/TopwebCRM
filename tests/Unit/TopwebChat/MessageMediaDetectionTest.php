<?php

use Webkul\TopwebChat\Models\Message;

it('classifies every known media type case-insensitively', function () {
    foreach (Message::MEDIA_TYPES as $type) {
        expect(Message::isMediaType($type))->toBeTrue()
            ->and(Message::isMediaType(strtoupper($type)))->toBeTrue();
    }

    expect(Message::isMediaType('text'))->toBeFalse()
        ->and(Message::isMediaType('chat'))->toBeFalse()
        ->and(Message::isMediaType(''))->toBeFalse();
});

it('detects media from flag, payload or type', function () {
    expect(Message::detectHasMedia(['type' => 'text', 'hasMedia' => true]))->toBeTrue()
        ->and(Message::detectHasMedia(['type' => 'text', 'media' => ['mimetype' => 'image/jpeg']]))->toBeTrue()
        ->and(Message::detectHasMedia(['type' => 'contact']))->toBeTrue()
        ->and(Message::detectHasMedia(['type' => 'vcard']))->toBeTrue()
        ->and(Message::detectHasMedia(['type' => 'text']))->toBeFalse()
        ->and(Message::detectHasMedia([]))->toBeFalse();
});
