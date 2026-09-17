<?php

namespace Webkul\Lead\Contracts;

use Webkul\Lead\Models\Lead;

interface AvailabilityResolver
{
    /**
     * Resolve the configured pool without exposing personal data.
     *
     * @return array{strategy: string, fallback_user_id: ?int, candidates: array<int, array{user_id: int, score: float, region: ?string}>, reason: string}
     */
    public function resolve(string $poolKey, Lead $lead, array $context = []): array;
}
