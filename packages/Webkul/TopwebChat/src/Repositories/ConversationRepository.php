<?php

namespace Webkul\TopwebChat\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Core\Eloquent\Repository;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\Message;
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
                'waiting' => $this->waitingForCustomer($query),
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
            'unassigned' => $query
                ->whereNull('assigned_user_id')
                ->where(fn (Builder $query) => $query
                    ->whereNull('lead_id')
                    ->orWhereHas('lead', fn (Builder $query) => $query->whereNull('user_id'))),
            'waiting' => $this->waitingForCustomer($mine($query)),
            default => $mine($query),
        };
    }

    private function waitingForCustomer(Builder $query): Builder
    {
        $conversationTable = DB::getTablePrefix().(new Conversation)->getTable();
        $messageTable = DB::getTablePrefix().(new Message)->getTable();

        if (! Schema::hasTable((new Message)->getTable())) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereRaw(
            "(SELECT direction FROM {$messageTable} AS queue_messages
                WHERE queue_messages.conversation_id = {$conversationTable}.id
                ORDER BY COALESCE(queue_messages.sent_at, queue_messages.created_at) DESC,
                         queue_messages.id DESC LIMIT 1) = ?",
            ['outgoing']
        );
    }

    /**
     * V-06: escopo (carteira) -> busca (nome/titulo) -> protecao (serializacao).
     * Telefone, e-mail e identificadores nunca sao pesquisaveis nem serializados.
     */
    public function search(User $user, string $term, int $limit = 10): Collection
    {
        $term = mb_substr(trim($term), 0, 60);

        if (mb_strlen($term) < 2) {
            return collect();
        }

        $query = $this->model
            ->newQuery()
            ->with(['person', 'lead'])
            ->latest('last_message_at')
            ->latest('id')
            ->whereNotNull('lead_id')
            ->limit(max(1, $limit));

        if ($user->role?->permission_type !== 'all') {
            $query->whereHas('lead', fn (Builder $query) => $query->where('user_id', $user->id));
        }

        $like = '%'.str_replace(['%', '_'], '', $term).'%';

        return $query->where(function (Builder $query) use ($like) {
            $query->whereHas('person', fn (Builder $query) => $query->where('name', 'like', $like))
                ->orWhereHas('lead', fn (Builder $query) => $query->where('title', 'like', $like));
        })->get();
    }
}
