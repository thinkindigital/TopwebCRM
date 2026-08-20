<?php

namespace Webkul\TopwebChat\Console\Commands;

use Illuminate\Console\Command;
use Webkul\TopwebChat\Jobs\ReconcileInstance;
use Webkul\TopwebChat\Jobs\SyncConversationHistory;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\Instance;

class ReconcileTopwebChat extends Command
{
    protected $signature = 'topweb-chat:reconcile {--history : Sync recent known conversations}';

    protected $description = 'Reconcile RyzeAPI instance state and known chat history';

    public function handle(): int
    {
        Instance::query()
            ->where('enabled', true)
            ->pluck('id')
            ->each(fn (int $id) => ReconcileInstance::dispatch($id));

        if ($this->option('history')) {
            Conversation::query()
                ->where('status', 'open')
                ->orderByDesc('last_message_at')
                ->limit(config('topweb-chat.history_batch_size'))
                ->pluck('id')
                ->each(fn (int $id) => SyncConversationHistory::dispatch($id));
        }

        $this->components->info('Topweb Chat reconciliation queued.');

        return self::SUCCESS;
    }
}
