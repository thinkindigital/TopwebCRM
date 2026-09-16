<?php

namespace Webkul\TopwebChat\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\User\Models\User;

class ConversationAccessService
{
    public function isAdministrator(User $user): bool
    {
        return $user->role?->permission_type === 'all';
    }

    public function canView(User $user, Conversation $conversation): bool
    {
        if ($this->isAdministrator($user)) {
            return true;
        }

        // D04: onde ha Lead, o dono e a unica autoridade operacional.
        if ($conversation->lead_id !== null && $lead = $conversation->lead) {
            return $lead->user_id !== null
                ? (int) $lead->user_id === (int) $user->id
                : (int) $conversation->assigned_user_id === (int) $user->id;
        }

        // Sem Lead, vale o legado (fila A3 cega na listagem).
        return $conversation->assigned_user_id === null
            || (int) $conversation->assigned_user_id === (int) $user->id;
    }

    public function authorizeView(User $user, Conversation $conversation): void
    {
        if (! $this->canView($user, $conversation)) {
            throw new AuthorizationException;
        }
    }

    public function canAssign(User $user, Conversation $conversation, int $targetUserId): bool
    {
        // D04: com Lead, a projecao deve espelhar a autoridade.
        if ($conversation->lead_id !== null && $lead = $conversation->lead) {
            if ($lead->user_id === null) {
                return $conversation->assigned_user_id === null
                    && (int) $targetUserId === (int) $user->id;
            }

            if ((int) $targetUserId !== (int) $lead->user_id) {
                return false;
            }

            return $this->isAdministrator($user)
                || (int) $lead->user_id === (int) $user->id;
        }

        if ($this->isAdministrator($user)) {
            return true;
        }

        return $conversation->assigned_user_id === null
            && $targetUserId === $user->id;
    }

    public function canUnassign(User $user, Conversation $conversation): bool
    {
        if ($this->isAdministrator($user)) {
            return true;
        }

        // D04: com Lead, so o dono devolve a conversa a fila.
        if ($conversation->lead_id !== null && $lead = $conversation->lead) {
            return $lead->user_id !== null
                && (int) $lead->user_id === (int) $user->id;
        }

        return $conversation->assigned_user_id === $user->id;
    }

    public function canAccessPerson(User $user, Person $person): bool
    {
        if ($this->isAdministrator($user)) {
            return true;
        }

        $authorizedUserIds = bouncer()->getAuthorizedUserIds();

        return $authorizedUserIds === null
            || in_array($person->user_id, $authorizedUserIds);
    }

    public function canAccessLead(User $user, Lead $lead): bool
    {
        if ($this->isAdministrator($user)) {
            return true;
        }

        $authorizedUserIds = bouncer()->getAuthorizedUserIds();

        return $authorizedUserIds === null
            || in_array($lead->user_id, $authorizedUserIds);
    }
}
