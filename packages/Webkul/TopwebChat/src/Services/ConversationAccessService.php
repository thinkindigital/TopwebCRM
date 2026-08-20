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
        return $this->isAdministrator($user)
            || $conversation->assigned_user_id === null
            || $conversation->assigned_user_id === $user->id;
    }

    public function authorizeView(User $user, Conversation $conversation): void
    {
        if (! $this->canView($user, $conversation)) {
            throw new AuthorizationException;
        }
    }

    public function canAssign(User $user, Conversation $conversation, int $targetUserId): bool
    {
        if ($this->isAdministrator($user)) {
            return true;
        }

        return $conversation->assigned_user_id === null
            && $targetUserId === $user->id;
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
