<?php

namespace Webkul\TopwebChat\Console\Commands;

use Illuminate\Console\Command;
use Webkul\TopwebChat\Jobs\ProjectLeadMedia as ProjectLeadMediaJob;
use Webkul\TopwebChat\Models\Message;

class ProjectLeadMedia extends Command
{
    protected $signature = 'topweb-chat:project-lead-media
        {--sync : Project immediately instead of dispatching queue jobs}
        {--limit=500 : Maximum messages inspected per execution}';

    protected $description = 'Project stored inbound WhatsApp media into native Lead files';

    public function handle(): int
    {
        $messages = Message::query()
            ->select('topweb_chat_messages.*')
            ->join(
                'topweb_chat_conversations',
                'topweb_chat_conversations.id',
                '=',
                'topweb_chat_messages.conversation_id'
            )
            ->leftJoin(
                'topweb_chat_media_projections',
                'topweb_chat_media_projections.message_id',
                '=',
                'topweb_chat_messages.id'
            )
            ->where('topweb_chat_messages.direction', 'incoming')
            ->where('topweb_chat_messages.source', '!=', 'openwa_history')
            ->whereIn('topweb_chat_messages.type', Message::MEDIA_TYPES)
            ->whereNotNull('topweb_chat_conversations.lead_id')
            ->whereNull('topweb_chat_media_projections.id')
            ->orderBy('topweb_chat_messages.id')
            ->limit(max(1, (int) $this->option('limit')))
            ->pluck('topweb_chat_messages.id');

        foreach ($messages as $messageId) {
            $job = new ProjectLeadMediaJob($messageId);

            if ($this->option('sync')) {
                dispatch_sync($job);
            } else {
                dispatch($job);
            }
        }

        $this->components->info("Lead media projection candidates: {$messages->count()}.");

        return self::SUCCESS;
    }
}
