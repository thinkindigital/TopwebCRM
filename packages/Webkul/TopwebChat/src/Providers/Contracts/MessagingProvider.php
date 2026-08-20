<?php

namespace Webkul\TopwebChat\Providers\Contracts;

use Carbon\CarbonInterface;
use Webkul\TopwebChat\Models\Instance;

interface MessagingProvider
{
    public function sendText(Instance $instance, string $recipient, string $message): array;

    public function connectionStatus(Instance $instance): string;

    public function history(
        Instance $instance,
        string $recipient,
        int $count = 100,
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null
    ): array;

    public function markChatRead(
        Instance $instance,
        string $recipient,
        bool $read = true
    ): void;

    public function configureWebhook(
        Instance $instance,
        string $url,
        string $authorization
    ): void;
}
