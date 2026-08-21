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
        'session_uuid',
        'token',
        'webhook_secret',
        'base_url',
        'status',
        'enabled',
        'engine_loaded',
        'restriction',
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
        'engine_loaded' => 'boolean',
        'last_connected_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'restriction' => 'array',
    ];

    public function conversations()
    {
        return $this->hasMany(ConversationProxy::modelClass());
    }

    public function isOpenWA(): bool
    {
        return $this->provider === 'openwa';
    }

    public function getBaseUrl(): string
    {
        return $this->base_url ?? config('topweb-chat.base_url');
    }

    public function getApiKey(): string
    {
        return $this->token;
    }

    public function getWebhookSecret(): string
    {
        return $this->webhook_secret;
    }
}
