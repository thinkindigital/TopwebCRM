<?php

namespace Webkul\TopwebChat\Support;

use Illuminate\Support\Str;

final class TopwebChatError
{
    public const FIL_INVALID_TYPE = 'FIL-1001';

    public const FIL_SIZE_LIMIT = 'FIL-6001';

    public const FIL_BATCH_SIZE_LIMIT = 'FIL-6002';

    public const FIL_PROCESSING_REJECTED = 'FIL-4001';

    public const STO_OBJECT_NOT_FOUND = 'STO-2001';

    public const NET_CONNECTION_FAILED = 'NET-3001';

    public const API_TIMEOUT = 'API-3001';

    public const API_OPERATION_REJECTED = 'API-4001';

    public const API_UNEXPECTED_RESPONSE = 'API-5001';

    public const API_RATE_LIMITED = 'API-6001';

    public const API_PROVIDER_BUSY = 'API-7001';

    public const API_UNCLASSIFIED_FAILURE = 'API-9001';

    public const WHK_PROCESSING_FAILED = 'WHK-5001';

    public static function canonical(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (in_array($value, self::catalog(), true)) {
            return $value;
        }

        return match ($value) {
            'F4003', 'too_large' => self::FIL_SIZE_LIMIT,
            'F4004', 'batch_too_large' => self::FIL_BATCH_SIZE_LIMIT,
            'type_not_supported' => self::FIL_INVALID_TYPE,
            'rejected' => self::FIL_PROCESSING_REJECTED,
            'A5001', 'A5002', 'provider_request_outcome_unknown' => self::API_TIMEOUT,
            'A5003', 'provider_request_rejected', 'provider_status_failed' => self::API_OPERATION_REJECTED,
            'provider_rate_limited', 'provider_rate_limit_exhausted' => self::API_RATE_LIMITED,
            'provider_instance_not_connected' => self::API_PROVIDER_BUSY,
            'media_file_missing' => self::STO_OBJECT_NOT_FOUND,
            default => null,
        };
    }

    public static function forProvider(?int $statusCode, bool $outcomeUnknown = false): string
    {
        return match (true) {
            $statusCode === 429 => self::API_RATE_LIMITED,
            $statusCode === 408 => self::API_TIMEOUT,
            $statusCode !== null && $statusCode >= 500 => self::API_UNEXPECTED_RESPONSE,
            $statusCode !== null && $statusCode >= 400 => self::API_OPERATION_REJECTED,
            $outcomeUnknown => self::API_UNCLASSIFIED_FAILURE,
            default => self::API_UNCLASSIFIED_FAILURE,
        };
    }

    public static function traceId(): string
    {
        return (string) Str::ulid();
    }

    public static function translationKey(?string $value): ?string
    {
        $code = self::canonical($value);

        return $code === null
            ? null
            : 'topweb_chat::app.messages.error_'.strtolower(str_replace('-', '_', $code));
    }

    public static function shortTrace(?string $traceId): ?string
    {
        return $traceId ? substr($traceId, 0, 8) : null;
    }

    public static function definition(string $code): array
    {
        return match ($code) {
            self::FIL_INVALID_TYPE => [
                'event' => 'attachment.validation.invalid_type',
                'severity' => 'warning',
                'retryable' => false,
                'http_status' => 422,
            ],
            self::FIL_SIZE_LIMIT => [
                'event' => 'attachment.validation.size_limit',
                'severity' => 'warning',
                'retryable' => false,
                'http_status' => 422,
            ],
            self::FIL_BATCH_SIZE_LIMIT => [
                'event' => 'attachment.validation.batch_size_limit',
                'severity' => 'warning',
                'retryable' => false,
                'http_status' => 422,
            ],
            self::FIL_PROCESSING_REJECTED => [
                'event' => 'attachment.processing.rejected',
                'severity' => 'warning',
                'retryable' => false,
                'http_status' => 422,
            ],
            self::STO_OBJECT_NOT_FOUND => [
                'event' => 'attachment.storage.object_missing',
                'severity' => 'error',
                'retryable' => false,
                'http_status' => 404,
            ],
            self::NET_CONNECTION_FAILED => [
                'event' => 'client.connection.failed',
                'severity' => 'warning',
                'retryable' => true,
                'http_status' => null,
            ],
            self::API_TIMEOUT => [
                'event' => 'message.send.provider_timeout',
                'severity' => 'warning',
                'retryable' => 'conditional',
                'http_status' => 504,
            ],
            self::API_OPERATION_REJECTED => [
                'event' => 'message.send.provider_rejected',
                'severity' => 'warning',
                'retryable' => false,
                'http_status' => 502,
            ],
            self::API_UNEXPECTED_RESPONSE => [
                'event' => 'message.send.provider_unexpected_response',
                'severity' => 'error',
                'retryable' => 'conditional',
                'http_status' => 502,
            ],
            self::API_RATE_LIMITED => [
                'event' => 'message.send.provider_rate_limited',
                'severity' => 'warning',
                'retryable' => true,
                'http_status' => 429,
            ],
            self::API_PROVIDER_BUSY => [
                'event' => 'message.send.provider_busy',
                'severity' => 'warning',
                'retryable' => true,
                'http_status' => 503,
            ],
            self::API_UNCLASSIFIED_FAILURE => [
                'event' => 'message.send.provider_failure.unclassified',
                'severity' => 'error',
                'retryable' => 'conditional',
                'http_status' => 502,
            ],
            self::WHK_PROCESSING_FAILED => [
                'event' => 'webhook.processing.failed',
                'severity' => 'error',
                'retryable' => true,
                'http_status' => 500,
            ],
            default => [
                'event' => 'message.send.provider_failure.unclassified',
                'severity' => 'error',
                'retryable' => 'conditional',
                'http_status' => 502,
            ],
        };
    }

    /** @return list<string> */
    private static function catalog(): array
    {
        return [
            self::FIL_INVALID_TYPE,
            self::FIL_SIZE_LIMIT,
            self::FIL_BATCH_SIZE_LIMIT,
            self::FIL_PROCESSING_REJECTED,
            self::STO_OBJECT_NOT_FOUND,
            self::NET_CONNECTION_FAILED,
            self::API_TIMEOUT,
            self::API_OPERATION_REJECTED,
            self::API_UNEXPECTED_RESPONSE,
            self::API_RATE_LIMITED,
            self::API_PROVIDER_BUSY,
            self::API_UNCLASSIFIED_FAILURE,
            self::WHK_PROCESSING_FAILED,
        ];
    }
}
