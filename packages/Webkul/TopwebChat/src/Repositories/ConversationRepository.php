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

        // D04: carteira por dono do Lead; sem Lead, vale o legado.
        $mine = fn (Builder $query) => $query->where(function (Builder $query) use ($user) {
            $query->whereHas('lead', fn (Builder $query) => $query->where('user_id', $user->id))
                ->orWhere(fn (Builder $query) => $query
                    ->whereNull('lead_id')
                    ->where('assigned_user_id', $user->id));
        });

        return match ($queue) {
            'unassigned' => $query->whereNull('lead_id')->whereNull('assigned_user_id'),
            default => $mine($query),
        };
    }
}
