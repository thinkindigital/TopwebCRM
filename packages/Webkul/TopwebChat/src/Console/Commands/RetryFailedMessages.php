<?php

namespace Webkul\TopwebChat\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Webkul\TopwebChat\Jobs\SendMessage;
use Webkul\TopwebChat\Models\Message;

class RetryFailedMessages extends Command
{
    protected $signature = 'topweb-chat:retry-failed';

    protected $description = 'Requeue outbound failures caused by disconnected sessions that are ready again';

    public function handle(): int
    {
        $window = now()->subHours((int) config('topweb-chat.retry_failed_window_hours', 72));
        $batch = (int) config('topweb-chat.retry_failed_batch_size', 100);
        $maxAttempts = (int) config('topweb-chat.send_max_attempts', 5);

        $ids = Message::query()
            ->where('direction', 'outgoing')
            ->where('status', 'failed')
            ->where('last_error', 'provider_instance_not_connected')
            ->where('failed_at', '>=', $window)
            ->where('attempts', '<', $maxAttempts)
            ->orderBy('failed_at')
            ->limit($batch)
            ->pluck('id');

        $requeued = 0;

        foreach ($ids as $id) {
            $claimed = DB::transaction(function () use ($id) {
                $message = Message::query()
                    ->with('conversation.instance')
                    ->lockForUpdate()
                    ->find($id);

                if (! $message || $message->status !== 'failed') {
                    return false;
                }

                $instance = $message->conversation?->instance;

                if (! $instance?->enabled || $instance->status !== 'ready') {
                    return false;
                }

                $message->update([
                    'status' => 'queued',
                    'failed_at' => null,
                    'last_error' => null,
                ]);

                return true;
            });

            if ($claimed) {
                SendMessage::dispatch($id);
                $requeued++;
            }
        }

        Log::info('TopwebChat failed messages requeued.', ['count' => $requeued]);

        $this->components->info("TopwebChat failed messages requeued: {$requeued}.");

        return self::SUCCESS;
    }
}
