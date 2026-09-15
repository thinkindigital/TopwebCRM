<?php

namespace Webkul\Lead\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Webkul\User\Models\UserProxy;

class LeadDistributionPool extends Model
{
    protected $table = 'lead_distribution_pools';

    protected $fillable = [
        'pool_key',
        'strategy',
        'fallback_user_id',
        'constraints',
    ];

    protected $casts = [
        'constraints' => 'array',
    ];

    public function fallbackUser(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'fallback_user_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            UserProxy::modelClass(),
            'lead_distribution_pool_users',
            'pool_id',
            'user_id'
        )->withPivot(['enabled', 'region', 'score']);
    }
}
