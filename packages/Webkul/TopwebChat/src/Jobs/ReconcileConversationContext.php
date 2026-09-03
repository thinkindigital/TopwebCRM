<?php

namespace Webkul\TopwebChat\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Services\AttendanceService;
use Webkul\TopwebChat\Services\LeadMediaProjector;

class ReconcileConversationContext implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [15, 60, 180];

    public function __construct(public int $conversationId) {}

    public function handle(
        LeadMediaProjector $projector,
        AttendanceService $attendances
    ): void {
        $conversation = Conversation::query()->find($this->conversationId);

        if ($conversation?->lead_id) {
            $attendances->syncConversationAssociations($conversation);
            $projector->projectConversation($conversation);
        }
    }
}
