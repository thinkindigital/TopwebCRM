<?php

namespace Webkul\TopwebChat\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\TopwebChat\Contracts\Instance as InstanceContract;

class Instance extends Model implements InstanceContract
{
    protected $table = 'topweb_chat_instances';

    protected $fillable = [
        'name',
        'provider',
        'token',
        'webhook_secret',
        'status',
        'enabled',
        'last_connected_at',
        'last_synced_at',
    ];

    protected $hidden = [
        'token',
        'webhook_secret',
    ];

    protected $casts = [
        'token' => 'encrypted',
        'webhook_secret' => 'encrypted',
        'enabled' => 'boolean',
        'last_connected_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    public function conversations()
    {
        return $this->hasMany(ConversationProxy::modelClass());
    }
}
