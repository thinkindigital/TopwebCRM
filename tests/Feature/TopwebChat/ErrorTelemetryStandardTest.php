<?php

use Webkul\TopwebChat\Support\TopwebChatError;

it('uses the v1.1 stable categories for transport, storage and webhooks', function () {
    expect(TopwebChatError::NET_CONNECTION_TIMEOUT)->toBe('NET-3001')
        ->and(TopwebChatError::NET_UNCLASSIFIED_FAILURE)->toBe('NET-9001')
        ->and(TopwebChatError::STO_UNAVAILABLE)->toBe('STO-3001')
        ->and(TopwebChatError::WHK_MALFORMED)->toBe('WHK-1001')
        ->and(TopwebChatError::WHK_INVALID_SIGNATURE)->toBe('WHK-2001')
        ->and(TopwebChatError::WHK_EVENT_REJECTED)->toBe('WHK-4001')
        ->and(TopwebChatError::canonical('NET-3001'))->toBe(TopwebChatError::NET_CONNECTION_TIMEOUT)
        ->and(TopwebChatError::canonical('F4003'))->toBe(TopwebChatError::FIL_SIZE_LIMIT);
});

it('keeps severity, retryability and HTTP defaults independent from codes', function () {
    expect(TopwebChatError::definition(TopwebChatError::NET_CONNECTION_TIMEOUT))
        ->toMatchArray([
            'severity' => 'warning',
            'retryable' => true,
            'http_status' => 504,
        ])
        ->and(TopwebChatError::definition(TopwebChatError::WHK_INVALID_SIGNATURE))
        ->toMatchArray([
            'severity' => 'warning',
            'retryable' => false,
            'http_status' => 401,
        ]);
});

it('exposes only canonical codes for client telemetry validation', function () {
    expect(TopwebChatError::codes())
        ->toContain(TopwebChatError::MSG_RETRY_NOT_AVAILABLE)
        ->toContain(TopwebChatError::WHK_PROCESSING_FAILED)
        ->not->toContain('F4003')
        ->not->toContain('A5003');
});
