<?php

namespace Webkul\Lead\Services;

use InvalidArgumentException;
use Webkul\Lead\Contracts\AvailabilityResolver;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\LeadDistributionPool;

class ConfiguredAvailabilityResolver implements AvailabilityResolver
{
    public function resolve(string $poolKey, Lead $lead, array $context = []): array
    {
        $pool = LeadDistributionPool::query()
            ->with(['users' => fn ($query) => $query->where('users.status', true)])
            ->where('pool_key', $poolKey)
            ->first();

        if (! $pool) {
            throw new InvalidArgumentException("Distribution pool [{$poolKey}] is not configured.");
        }

        $leadRegion = $context['region'] ?? null;
        $allowedRegions = $pool->constraints['regions'] ?? [];

        if ($allowedRegions !== [] && ! in_array($leadRegion, $allowedRegions, true)) {
            return [
                'strategy' => $pool->strategy,
                'fallback_user_id' => $pool->fallback_user_id,
                'candidates' => [],
                'reason' => 'pool_region_constraint_mismatch',
            ];
        }

        $candidates = $pool->users
            ->filter(function ($user) use ($leadRegion) {
                $membership = $user->pivot;

                return $membership->enabled
                    && ($membership->region === null || $leadRegion === null || $membership->region === $leadRegion);
            })
            ->map(fn ($user) => [
                'user_id' => (int) $user->id,
                'score' => (float) $user->pivot->score,
                'region' => $user->pivot->region,
            ])
            ->values()
            ->all();

        $fallbackUserId = $pool->users
            ->first(fn ($user) => $user->id === $pool->fallback_user_id && $user->pivot->enabled)
            ?->id;

        return [
            'strategy' => $pool->strategy,
            'fallback_user_id' => $fallbackUserId,
            'candidates' => $candidates,
            'reason' => $candidates === [] ? 'no_available_pool_member' : 'configured_pool_members',
        ];
    }
}
