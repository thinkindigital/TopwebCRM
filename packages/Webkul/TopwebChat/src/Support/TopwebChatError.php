<?php

namespace Webkul\TopwebChat\Support;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

final class TopwebChatError
{
    public const FIL_INVALID_TYPE = 'FIL-1001';

    public const FIL_SIZE_LIMIT = 'FIL-6001';

    public const FIL_BATCH_SIZE_LIMIT = 'FIL-6002';

    public const FIL_PROCESSING_REJECTED = 'FIL-4001';

    public const FIL_FILE_BUSY = 'FIL-7001';

    public const STO_OBJECT_NOT_FOUND = 'STO-2001';

    public const NET_CONNECTION_TIMEOUT = 'NET-3001';

    public const NET_UNCLASSIFIED_FAILURE = 'NET-9001';

    public const NET_CONNECTION_FAILED = self::NET_UNCLASSIFIED_FAILURE;

    public const STO_UNAVAILABLE = 'STO-3001';

    public const API_TIMEOUT = 'API-3001';

    public const API_OPERATION_REJECTED = 'API-4001';

    public const API_UNEXPECTED_RESPONSE = 'API-5001';

    public const API_RATE_LIMITED = 'API-6001';

    public const API_PROVIDER_BUSY = 'API-7001';

    public const API_UNCLASSIFIED_FAILURE = 'API-9001';

    public const WHK_PROCESSING_FAILED = 'WHK-5001';

    public const WHK_MALFORMED = 'WHK-1001';

    public const WHK_INVALID_SIGNATURE = 'WHK-2001';

    public const WHK_EVENT_REJECTED = 'WHK-4001';

    public const MSG_RETRY_NOT_AVAILABLE = 'MSG-4001';

    public static function canonical(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (in_array($value, self::codes(), true) || $value === self::FIL_FILE_BUSY) {
            return $value;
        }

        return match ($value) {
            'F4003' => self::FIL_FILE_BUSY,
            'too_large' => self::FIL_SIZE_LIMIT,
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

    public static function envelope(
        ?string $value,
        ?string $traceId,
        string $fallbackCode = self::API_UNCLASSIFIED_FAILURE
    ): ?array {
        if (($value === null || $value === '') && $traceId === null) {
            return null;
        }

        $code = self::canonical($value) ?? $fallbackCode;
        $translationKey = self::translationKey($code);
        $friendlyMessage = $translationKey !== null && Lang::has($translationKey)
            ? trans($translationKey)
            : trans('topweb_chat::app.messages.send_failed');
        $definition = self::definition($code);

        return [
            'code' => $code,
            'message' => $friendlyMessage,
            'error_code' => $code,
            'trace_id' => $traceId,
            'friendly_message' => $friendlyMessage,
            'technical_event' => $definition['event'],
        ];
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
            self::FIL_FILE_BUSY => [
                'event' => 'attachment.processing.busy',
                'severity' => 'warning',
                'retryable' => true,
                'http_status' => 409,
            ],
            self::STO_OBJECT_NOT_FOUND => [
                'event' => 'attachment.storage.object_missing',
                'severity' => 'error',
                'retryable' => false,
                'http_status' => 404,
            ],
            self::NET_CONNECTION_TIMEOUT => [
                'event' => 'client.connection.timeout',
                'severity' => 'warning',
                'retryable' => true,
                'http_status' => 504,
            ],
            self::NET_UNCLASSIFIED_FAILURE => [
                'event' => 'client.connection.unclassified',
                'severity' => 'warning',
                'retryable' => true,
                'http_status' => null,
            ],
            self::STO_UNAVAILABLE => [
                'event' => 'attachment.storage.unavailable',
                'severity' => 'error',
                'retryable' => 'conditional',
                'http_status' => 503,
            ],
            self::WHK_MALFORMED => [
                'event' => 'webhook.validation.malformed',
                'severity' => 'warning',
                'retryable' => false,
                'http_status' => 422,
            ],
            self::WHK_INVALID_SIGNATURE => [
                'event' => 'webhook.security.invalid_signature',
                'severity' => 'warning',
                'retryable' => false,
                'http_status' => 401,
            ],
            self::WHK_EVENT_REJECTED => [
                'event' => 'webhook.validation.event_rejected',
                'severity' => 'warning',
                'retryable' => false,
                'http_status' => 422,
            ],
            self::MSG_RETRY_NOT_AVAILABLE => [
                'event' => 'message.retry.rejected',
                'severity' => 'warning',
                'retryable' => false,
                'http_status' => 409,
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
    public static function codes(): array
    {
        return [
            self::FIL_INVALID_TYPE,
            self::FIL_SIZE_LIMIT,
            self::FIL_BATCH_SIZE_LIMIT,
            self::FIL_PROCESSING_REJECTED,
            self::STO_OBJECT_NOT_FOUND,
            self::NET_CONNECTION_TIMEOUT,
            self::NET_UNCLASSIFIED_FAILURE,
            self::STO_UNAVAILABLE,
            self::API_TIMEOUT,
            self::API_OPERATION_REJECTED,
            self::API_UNEXPECTED_RESPONSE,
            self::API_RATE_LIMITED,
            self::API_PROVIDER_BUSY,
            self::API_UNCLASSIFIED_FAILURE,
            self::WHK_MALFORMED,
            self::WHK_INVALID_SIGNATURE,
            self::WHK_EVENT_REJECTED,
            self::MSG_RETRY_NOT_AVAILABLE,
            self::WHK_PROCESSING_FAILED,
        ];
    }
}
