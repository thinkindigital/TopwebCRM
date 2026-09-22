<?php

namespace Webkul\TopwebChat\Exceptions;

use DomainException;
use Webkul\TopwebChat\Support\TopwebChatError;

class TopwebChatFailure extends DomainException
{
    public readonly string $traceId;

    public function __construct(
        public readonly string $errorCode,
        public readonly int $httpStatus = 409
    ) {
        $this->traceId = TopwebChatError::traceId();

        parent::__construct(
            trans(TopwebChatError::translationKey($errorCode))
        );
    }
}
