<?php

namespace Webkul\Lead\Models;

use Illuminate\Database\Eloquent\Model;

class LeadDistributionPoolUser extends Model
{
    protected $table = 'lead_distribution_pool_users';

    protected $fillable = [
        'pool_id',
        'user_id',
        'enabled',
        'region',
        'score',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'score' => 'float',
    ];
}
