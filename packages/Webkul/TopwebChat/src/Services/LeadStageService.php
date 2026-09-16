<?php

namespace Webkul\TopwebChat\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Webkul\Lead\Models\Pipeline;
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
        int $stageId,
        ?int $pipelineId = null
    ): void {
        DB::transaction(function () use ($conversation, $user, $stageId, $pipelineId): void {
            $this->conversationAccess->authorizeView($user, $conversation);

            abort_unless(
                $conversation->lead
                && bouncer()->hasPermission('leads.edit')
                && $this->conversationAccess->canAccessLead($user, $conversation->lead),
                403
            );

            $lead = $conversation->lead->newQuery()->lockForUpdate()->findOrFail($conversation->lead->id);
            $pipeline = $pipelineId
                ? Pipeline::query()->findOrFail($pipelineId)
                : $lead->pipeline;

            $stage = $pipeline->stages()->whereKey($stageId)->firstOrFail();
            $pipelineChanged = (int) $pipeline->id !== (int) $lead->lead_pipeline_id;

            Event::dispatch('lead.update.before', $lead->id);

            $attributes = ['lead_pipeline_stage_id'];
            $data = [
                'entity_type' => 'leads',
                'lead_pipeline_stage_id' => $stage->id,
            ];

            if ($pipelineChanged) {
                $data['lead_pipeline_id'] = $pipeline->id;
                $attributes[] = 'lead_pipeline_id';
            }

            $oldPipelineId = $lead->lead_pipeline_id;
            $oldStageId = $lead->lead_pipeline_stage_id;

            $lead = $this->leadRepository->update($data, $lead->id, $attributes);

            Event::dispatch('lead.update.after', $lead);
            Event::dispatch('topweb_chat.lead.stage_changed', [
                $conversation,
                $lead,
                $user,
            ]);

            if ($pipelineChanged) {
                Event::dispatch('topweb_chat.lead.pipeline_changed', [
                    $conversation,
                    $lead,
                    $user,
                ]);

                Log::info('TopwebChat lead pipeline changed.', [
                    'conversation_id' => $conversation->id,
                    'lead_id' => $lead->id,
                    'actor_user_id' => $user->id,
                    'old_pipeline_id' => $oldPipelineId,
                    'new_pipeline_id' => $pipeline->id,
                    'old_stage_id' => $oldStageId,
                    'new_stage_id' => $stage->id,
                ]);
            }
        });
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function stagesForPipeline(Conversation $conversation, User $user, Pipeline $pipeline): array
    {
        $this->conversationAccess->authorizeView($user, $conversation);

        abort_unless(
            $conversation->lead
            && bouncer()->hasPermission('topweb_chat.inbox.stage')
            && bouncer()->hasPermission('leads.edit')
            && $this->conversationAccess->canAccessLead($user, $conversation->lead),
            403
        );

        return $pipeline->stages()
            ->orderBy('sort_order')
            ->get(['id', 'name'])
            ->map(fn ($stage) => ['id' => $stage->id, 'name' => $stage->name])
            ->all();
    }
}
