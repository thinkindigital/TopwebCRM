<?php

namespace Webkul\TopwebChat\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Webkul\Core\Eloquent\Repository;
use Webkul\User\Models\User;

class ConversationRepository extends Repository
{
    public function model(): string
    {
        return 'Webkul\TopwebChat\Contracts\Conversation';
    }

    public function accessibleQuery(User $user, string $queue = 'mine'): Builder
    {
        $query = $this->model
            ->newQuery()
            ->with(['person', 'lead', 'assignedUser', 'instance'])
            ->where('status', 'open')
            ->latest('last_message_at');

        if ($user->role?->permission_type === 'all') {
            return match ($queue) {
                'mine' => $query->where('assigned_user_id', $user->id),
                'unassigned' => $query->whereNull('assigned_user_id'),
                default => $query,
            };
        }

        return match ($queue) {
            'unassigned' => $query->whereNull('assigned_user_id'),
            default => $query->where('assigned_user_id', $user->id),
        };
    }
}
