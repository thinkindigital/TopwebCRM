<?php

namespace Webkul\TopwebChat\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Webkul\Lead\Models\Lead;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\User\Models\User;

class LeadOwnershipService
{
    public function __construct(protected ConversationAccessService $access) {}

    public function transfer(
        Conversation $conversation,
        User $actor,
        int $targetUserId
    ): Lead {
        abort_unless(
            bouncer()->hasPermission('topweb_chat.inbox.assign')
            && bouncer()->hasPermission('leads.edit'),
            403
        );

        $this->access->authorizeView($actor, $conversation);

        $target = User::query()
            ->whereKey($targetUserId)
            ->where('status', 1)
            ->first();

        abort_unless($target, 422);
        abort_unless($conversation->lead_id !== null, 422);

        return DB::transaction(function () use ($conversation, $actor, $target) {
            $lead = Lead::query()
                ->lockForUpdate()
                ->findOrFail($conversation->lead_id);

            abort_unless($this->access->canAccessLead($actor, $lead), 403);

            // Lock the complete projection set before changing the authority.
            Conversation::query()
                ->where('lead_id', $lead->id)
                ->lockForUpdate()
                ->get();

            $oldOwnerId = $lead->user_id;
            if ((int) $oldOwnerId !== (int) $target->id) {
                // The observer records the normal Lead audit; the explicit
                // projection update below is the transaction's final
                // reconciliation, including an already-owned Lead.
                $lead->forceFill(['user_id' => $target->id])->save();
            }

            Conversation::query()
                ->where('lead_id', $lead->id)
                ->update(['assigned_user_id' => $target->id]);

            Log::info('TopwebChat Lead ownership transferred.', [
                'conversation_id' => $conversation->id,
                'lead_id' => $lead->id,
                'actor_user_id' => $actor->id,
                'old_owner_id' => $oldOwnerId,
                'new_owner_id' => $target->id,
            ]);

            Event::dispatch('topweb_chat.lead.owner_transferred', [
                'lead_id' => $lead->id,
                'old_owner_id' => $oldOwnerId,
                'new_owner_id' => $target->id,
                'actor_user_id' => $actor->id,
            ]);

            return $lead->fresh();
        });
    }
}
