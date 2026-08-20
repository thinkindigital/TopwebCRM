<?php

namespace Webkul\TopwebChat\Exceptions;

use RuntimeException;

class ProviderRequestException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $outcomeUnknown = false,
        public readonly ?int $statusCode = null,
        public readonly int $retryAfter = 0
    ) {
        parent::__construct($message);
    }
}
