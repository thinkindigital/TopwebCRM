<?php

namespace Webkul\TopwebChat\Services;

use Illuminate\Support\Facades\Event;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\User\Models\User;

class LeadStageService
{
    public function __construct(
        protected LeadRepository $leadRepository,
        protected ConversationAccessService $conversationAccess
    ) {}

    public function move(
        Conversation $conversation,
        User $user,
        int $stageId
    ): void {
        $this->conversationAccess->authorizeView($user, $conversation);

        abort_unless(
            $conversation->lead
            && bouncer()->hasPermission('leads.edit')
            && $this->conversationAccess->canAccessLead($user, $conversation->lead),
            403
        );

        $stage = $conversation->lead->pipeline
            ->stages()
            ->whereKey($stageId)
            ->firstOrFail();

        Event::dispatch('lead.update.before', $conversation->lead->id);

        $lead = $this->leadRepository->update([
            'entity_type' => 'leads',
            'lead_pipeline_stage_id' => $stage->id,
        ], $conversation->lead->id, ['lead_pipeline_stage_id']);

        Event::dispatch('lead.update.after', $lead);
        Event::dispatch('topweb_chat.lead.stage_changed', [
            $conversation,
            $lead,
            $user,
        ]);
    }
}
