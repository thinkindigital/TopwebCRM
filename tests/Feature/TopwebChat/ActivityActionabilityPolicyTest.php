<?php

// E14-R2 S3: classificação central de actionability de Activities.

use Webkul\Activity\Models\Activity;
use Webkul\TopwebChat\Services\ActivityActionabilityPolicy;

function stubActivity(string $type, bool $done = false): Activity
{
    return new Activity(['type' => $type, 'is_done' => $done]);
}

it('treats commercial types as actionable', function (string $type) {
    expect(ActivityActionabilityPolicy::isActionable(stubActivity($type)))->toBeTrue();
})->with(['call', 'meeting', 'visit', 'follow-up', 'followup', 'task', 'Call']);

it('never treats record, system or unknown types as actionable', function (string $type) {
    expect(ActivityActionabilityPolicy::isActionable(stubActivity($type)))->toBeFalse();
})->with(['note', 'system', 'file', 'mystery-type', '']);

it('never treats finished activities as actionable', function () {
    expect(ActivityActionabilityPolicy::isActionable(stubActivity('call', true)))->toBeFalse();
});

it('exposes a stable category per type', function () {
    expect(ActivityActionabilityPolicy::category('visit'))->toBe('actionable')
        ->and(ActivityActionabilityPolicy::category('note'))->toBe('record')
        ->and(ActivityActionabilityPolicy::category('system'))->toBe('system')
        ->and(ActivityActionabilityPolicy::category('mystery-type'))->toBe('unknown');
});
