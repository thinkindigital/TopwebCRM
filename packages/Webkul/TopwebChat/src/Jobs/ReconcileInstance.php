<?php

namespace Webkul\TopwebChat\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Webkul\TopwebChat\Exceptions\ProviderRequestException;
use Webkul\TopwebChat\Models\Instance;
use Webkul\TopwebChat\Providers\Contracts\MessagingProvider;

class ReconcileInstance implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30, 120];

    public function __construct(public int $instanceId) {}

    public function handle(MessagingProvider $provider): void
    {
        $instance = Instance::query()->findOrFail($this->instanceId);
        try {
            $status = $provider->connectionStatus($instance);
        } catch (ProviderRequestException $exception) {
            if ($exception->statusCode === 429) {
                $this->release($exception->retryAfter);

                return;
            }

            throw $exception;
        }

        $instance->update([
            'status' => $status,
            'last_connected_at' => $status === 'connected'
                ? now()
                : $instance->last_connected_at,
            'last_synced_at' => now(),
        ]);
    }
}
