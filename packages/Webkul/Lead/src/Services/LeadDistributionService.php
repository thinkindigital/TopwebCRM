<?php

namespace Webkul\Lead\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Webkul\Lead\Models\Lead;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\User\Models\User;

class LeadDistributionService
{
    public function assignRoundRobin(
        Lead $lead,
        array $eligibleUserIds,
        ?int $conversationId = null,
        string $poolKey = 'default',
        ?int $fallbackUserId = null,
    ): User {
        return DB::transaction(function () use ($lead, $eligibleUserIds, $conversationId, $poolKey, $fallbackUserId) {
            $candidateIds = collect($eligibleUserIds)
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values();

            $eligibleUsers = User::query()
                ->whereIn('id', $candidateIds)
                ->where('status', true)
                ->orderBy('id')
                ->get();

            $result = 'assigned';
            $state = null;

            if ($eligibleUsers->isEmpty()) {
                $nextUser = User::query()
                    ->whereKey($fallbackUserId)
                    ->where('status', true)
                    ->first();

                if (! $nextUser) {
                    throw new InvalidArgumentException('No eligible user is available for lead distribution.');
                }

                $result = 'fallback';
            } else {
                $state = DB::table('lead_distribution_states')
                    ->where('pool_key', $poolKey)
                    ->lockForUpdate()
                    ->first();

                if (! $state) {
                    DB::table('lead_distribution_states')->insert([
                        'pool_key' => $poolKey,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $state = DB::table('lead_distribution_states')
                        ->where('pool_key', $poolKey)
                        ->lockForUpdate()
                        ->first();
                }

                $nextUser = $eligibleUsers->first(
                    fn (User $user) => $state->last_user_id === null || $user->id > $state->last_user_id
                ) ?? $eligibleUsers->first();
            }

            if ($conversationId !== null) {
                $conversation = Conversation::query()
                    ->lockForUpdate()
                    ->findOrFail($conversationId);

                if ((int) $conversation->lead_id !== (int) $lead->id) {
                    throw new InvalidArgumentException('Conversation does not belong to the lead.');
                }
            }

            $lead->update(['user_id' => $nextUser->id]);

            if ($conversationId !== null) {
                Conversation::query()->whereKey($conversationId)->update([
                    'assigned_user_id' => $nextUser->id,
                ]);
            }

            if ($state) {
                DB::table('lead_distribution_states')
                    ->where('id', $state->id)
                    ->update(['last_user_id' => $nextUser->id, 'updated_at' => now()]);
            }

            DB::table('lead_distribution_decisions')->insert([
                'lead_id' => $lead->id,
                'selected_user_id' => $nextUser->id,
                'strategy' => 'round_robin',
                'pool_key' => $poolKey,
                'candidate_user_ids' => json_encode($eligibleUsers->pluck('id')->values()),
                'result' => $result,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $nextUser;
        });
    }
}
