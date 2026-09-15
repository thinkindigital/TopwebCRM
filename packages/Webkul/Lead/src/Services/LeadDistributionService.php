<?php

namespace Webkul\Lead\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Webkul\Lead\Contracts\AvailabilityResolver;
use Webkul\Lead\Models\Lead;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\User\Models\User;

class LeadDistributionService
{
    public function __construct(private readonly AvailabilityResolver $availabilityResolver) {}

    public function assign(
        Lead $lead,
        string $poolKey = 'default',
        array $context = [],
        ?int $conversationId = null,
    ): User {
        $resolution = $this->availabilityResolver->resolve($poolKey, $lead, $context);

        return $this->assignRoundRobin(
            $lead,
            $resolution['candidates'],
            $conversationId,
            $poolKey,
            $resolution['fallback_user_id'],
            null,
            $resolution['strategy'],
            $resolution['reason'],
        );
    }

    public function assignRoundRobin(
        Lead $lead,
        array $eligibleUserIds,
        ?int $conversationId = null,
        string $poolKey = 'default',
        ?int $fallbackUserId = null,
        ?array $availableUserIds = null,
        ?string $strategy = null,
        string $reason = 'runtime_candidates',
    ): User {
        return DB::transaction(function () use ($lead, $eligibleUserIds, $conversationId, $poolKey, $fallbackUserId, $availableUserIds, $strategy, $reason) {
            $hasScoredCandidates = collect($eligibleUserIds)->contains(fn ($candidate) => is_array($candidate));
            $candidates = collect($eligibleUserIds)
                ->map(function ($candidate) {
                    if (is_array($candidate)) {
                        return [
                            'user_id' => (int) ($candidate['user_id'] ?? 0),
                            'score' => (float) ($candidate['score'] ?? 0),
                        ];
                    }

                    return ['user_id' => (int) $candidate, 'score' => 0.0];
                })
                ->filter(fn (array $candidate) => $candidate['user_id'] > 0)
                ->unique('user_id')
                ->values();

            $candidateIds = $candidates->pluck('user_id');

            if ($availableUserIds !== null) {
                $candidateIds = $candidateIds
                    ->intersect(collect($availableUserIds)->map(fn ($id) => (int) $id))
                    ->values();
            }

            $eligibleUsers = User::query()
                ->whereIn('id', $candidateIds)
                ->where('status', true)
                ->orderBy('id')
                ->get()
                ->sortByDesc(fn (User $user) => $candidates->firstWhere('user_id', $user->id)['score'])
                ->values();

            $resolvedStrategy = $strategy ?? ($hasScoredCandidates
                ? 'score_round_robin'
                : 'round_robin');

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
                    DB::table('lead_distribution_states')->insertOrIgnore([
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
                'strategy' => $resolvedStrategy,
                'pool_key' => $poolKey,
                'candidate_user_ids' => json_encode($eligibleUsers->pluck('id')->values()),
                'result' => $result,
                'reason' => $reason,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $nextUser;
        });
    }
}
