<?php

namespace Webkul\TopwebChat\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\TopwebChat\Jobs\ReconcileConversationContext;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\Instance;
use Webkul\TopwebChat\Providers\Contracts\MessagingProvider;
use Webkul\User\Models\User;

class ConversationService
{
    public function __construct(
        protected RemoteIdentityService $remoteIdentity,
        protected ContactResolverService $contactResolver,
        protected ConversationAccessService $access,
        protected MessagingProvider $provider,
        protected ?InboundLeadAssociationService $inboundLeadAssociation = null
    ) {}

    public function launchForPerson(
        Person $person,
        ?Lead $lead,
        User $user
    ): Conversation {
        $conversation = DB::transaction(function () use ($person, $lead, $user) {
            $instance = $this->singleEnabledInstance();
            $recipient = $this->recipientFromPerson($person);
            $remoteKey = $this->remoteIdentity->key($recipient);
            $conversation = Conversation::query()
                ->where('instance_id', $instance->id)
                ->where('remote_jid_key', $remoteKey)
                ->lockForUpdate()
                ->first();

            if (! $conversation) {
                return Conversation::query()->create([
                    'instance_id' => $instance->id,
                    'person_id' => $person->id,
                    'lead_id' => $lead?->id,
                    'remote_jid' => $recipient,
                    'remote_jid_key' => $remoteKey,
                    'status' => 'open',
                ]);
            }

            $this->access->authorizeView($user, $conversation);

            $conversation->update([
                'person_id' => $person->id,
                'lead_id' => $lead?->id ?? $conversation->lead_id,
                'status' => 'open',
                'closed_at' => null,
            ]);

            return $conversation->fresh();
        });

        if ($conversation->lead_id) {
            ReconcileConversationContext::dispatch($conversation->id);
        }

        return $conversation;
    }

    public function findOrCreateInbound(
        Instance $instance,
        string $remoteId,
        ?string $displayName = null
    ): Conversation {
        return DB::transaction(function () use ($instance, $remoteId, $displayName) {
            $conversation = $this->findForUpdate($instance, $remoteId);

            if ($conversation) {
                if ($conversation->status !== 'open') {
                    $conversation->update([
                        'status' => 'open',
                        'closed_at' => null,
                    ]);
                }

                if ($this->inboundLeadAssociation && ! $conversation->lead_id) {
                    $lead = $this->inboundLeadAssociation->associate($conversation->person);

                    if ($lead) {
                        $conversation->update(['lead_id' => $lead->id]);
                    }
                }

                return $conversation;
            }

            $remoteId = $this->resolvePrivacyIdentity($instance, $remoteId);
            $conversation = $this->findForUpdate($instance, $remoteId);

            if ($conversation) {
                if ($conversation->status !== 'open') {
                    $conversation->update([
                        'status' => 'open',
                        'closed_at' => null,
                    ]);
                }

                if ($this->inboundLeadAssociation && ! $conversation->lead_id) {
                    $lead = $this->inboundLeadAssociation->associate($conversation->person);

                    if ($lead) {
                        $conversation->update(['lead_id' => $lead->id]);
                    }
                }

                return $conversation;
            }

            $person = $this->contactResolver->resolve($remoteId, $displayName);
            $lead = $this->inboundLeadAssociation?->associate($person);

            return Conversation::query()->create([
                'instance_id' => $instance->id,
                'person_id' => $person->id,
                'lead_id' => $lead?->id,
                'remote_jid' => $remoteId,
                'remote_jid_key' => $this->remoteIdentity->key($remoteId),
                'status' => 'open',
            ]);
        });
    }

    public function find(Instance $instance, string $remoteId): ?Conversation
    {
        return $this->conversationQuery($instance, $remoteId)->first();
    }

    private function findForUpdate(Instance $instance, string $remoteId): ?Conversation
    {
        return $this->conversationQuery($instance, $remoteId)
            ->lockForUpdate()
            ->first();
    }

    private function conversationQuery(Instance $instance, string $remoteId)
    {
        return Conversation::query()
            ->where('instance_id', $instance->id)
            ->where('remote_jid_key', $this->remoteIdentity->key($remoteId));
    }

    private function resolvePrivacyIdentity(Instance $instance, string $remoteId): string
    {
        if (! str_ends_with(strtolower(trim($remoteId)), '@lid')) {
            return $remoteId;
        }

        return $this->provider->getContactPhone($instance, $remoteId) ?: $remoteId;
    }

    private function singleEnabledInstance(): Instance
    {
        $instances = Instance::query()->where('enabled', true)->get();

        if ($instances->count() !== 1) {
            throw new DomainException(
                $instances->isEmpty()
                    ? trans('topweb_chat::app.instances.none_enabled')
                    : trans('topweb_chat::app.instances.multiple_enabled')
            );
        }

        return $instances->first();
    }

    private function recipientFromPerson(Person $person): string
    {
        foreach ($person->contact_numbers ?? [] as $contactNumber) {
            $phone = $this->remoteIdentity->phone((string) ($contactNumber['value'] ?? ''));

            if ($phone) {
                return $phone;
            }
        }

        throw new DomainException(trans('topweb_chat::app.contacts.no_phone'));
    }
}
