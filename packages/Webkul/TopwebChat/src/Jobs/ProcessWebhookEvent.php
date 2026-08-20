<?php

namespace Webkul\TopwebChat\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;
use Webkul\TopwebChat\Models\WebhookEvent;
use Webkul\TopwebChat\Services\WebhookProcessor;

class ProcessWebhookEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30, 120];

    public function __construct(public int $eventId) {}

    public function handle(WebhookProcessor $processor): void
    {
        $event = WebhookEvent::query()->findOrFail($this->eventId);

        if ($event->status === 'processed') {
            return;
        }

        $event->increment('attempts');

        $processor->process($event);
    }

    public function failed(Throwable $exception): void
    {
        WebhookEvent::query()->whereKey($this->eventId)->update([
            'status' => 'failed',
            'failed_at' => now(),
            'last_error' => mb_substr($exception->getMessage(), 0, 1000),
        ]);
    }
}
