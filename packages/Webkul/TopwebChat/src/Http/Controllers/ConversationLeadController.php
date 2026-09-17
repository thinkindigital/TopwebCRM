<?php

namespace Webkul\TopwebChat\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Webkul\Lead\Models\Lead;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Services\ConversationAccessService;
use Webkul\TopwebChat\Services\InboundLeadAssociationService;

class ConversationLeadController
{
    public function __construct(
        protected ConversationAccessService $access,
        protected InboundLeadAssociationService $association
    ) {}

    public function link(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorizeMutation();
        $leadId = (int) $request->validate([
            'lead_id' => ['required', 'integer', 'exists:leads,id'],
        ])['lead_id'];
        $actor = auth()->guard('user')->user();

        DB::transaction(function () use ($conversation, $leadId, $actor) {
            $lockedConversation = Conversation::query()
                ->lockForUpdate()
                ->findOrFail($conversation->id);
            $this->access->authorizeView($actor, $lockedConversation);

            abort_unless($lockedConversation->person_id !== null, 422);

            $lead = Lead::query()->lockForUpdate()->findOrFail($leadId);
            abort_unless((int) $lead->person_id === (int) $lockedConversation->person_id, 403);
            abort_unless(
                $this->association->operationalLeads($lockedConversation->person)
                    ->contains('id', $lead->id),
                409
            );
            abort_unless(
                $lead->user_id === null || $this->access->canAccessLead($actor, $lead),
                403
            );

            $lockedConversation->update([
                'lead_id' => $lead->id,
                'assigned_user_id' => $lead->user_id,
            ]);
        });

        return back()->with('success', trans('topweb_chat::app.inbound.linked'));
    }

    public function create(Conversation $conversation): RedirectResponse
    {
        $this->authorizeMutation();
        $actor = auth()->guard('user')->user();

        DB::transaction(function () use ($conversation, $actor) {
            $lockedConversation = Conversation::query()
                ->lockForUpdate()
                ->findOrFail($conversation->id);
            $this->access->authorizeView($actor, $lockedConversation);

            abort_unless($lockedConversation->lead_id === null && $lockedConversation->person, 422);
            abort_unless(
                $this->association->operationalLeads($lockedConversation->person)->isEmpty(),
                409
            );

            $lead = $this->association->createForPerson($lockedConversation->person);
            $lockedConversation->update(['lead_id' => $lead->id]);
        });

        return back()->with('success', trans('topweb_chat::app.inbound.created'));
    }

    private function authorizeMutation(): void
    {
        abort_unless(
            bouncer()->hasPermission('topweb_chat.inbox.view')
            && bouncer()->hasPermission('leads.edit'),
            403
        );
    }
}
