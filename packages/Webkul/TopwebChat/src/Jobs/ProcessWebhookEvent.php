<?php

namespace Webkul\TopwebChat\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;
use Webkul\TopwebChat\Models\WebhookEvent;
use Webkul\TopwebChat\Services\WebhookProcessor;
use Webkul\TopwebChat\Support\TopwebChatError;

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
        $traceId = TopwebChatError::traceId();
        $definition = TopwebChatError::definition(TopwebChatError::WHK_PROCESSING_FAILED);

        WebhookEvent::query()->whereKey($this->eventId)->update([
            'status' => 'failed',
            'failed_at' => now(),
            'last_error' => 'webhook_processing_failed',
            'error_code' => TopwebChatError::WHK_PROCESSING_FAILED,
            'trace_id' => $traceId,
        ]);

        logger()->error('TopwebChat webhook processing failed.', [
            'error_code' => TopwebChatError::WHK_PROCESSING_FAILED,
            'trace_id' => $traceId,
            'technical_event' => $definition['event'],
            'severity' => $definition['severity'],
            'retryable' => $definition['retryable'],
            'http_status' => $definition['http_status'],
            'operation' => 'webhook.process',
            'webhook_event_id' => $this->eventId,
        ]);
    }
}
