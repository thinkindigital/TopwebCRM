<?php

namespace Webkul\TopwebChat\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\TopwebChat\Contracts\WebhookEvent as WebhookEventContract;

class WebhookEvent extends Model implements WebhookEventContract
{
    protected $table = 'topweb_chat_webhook_events';

    protected $fillable = [
        'instance_id',
        'event_key',
        'event_type',
        'payload',
        'status',
        'attempts',
        'processed_at',
        'failed_at',
        'last_error',
    ];

    protected $casts = [
        'payload' => 'encrypted:array',
        'processed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function instance()
    {
        return $this->belongsTo(InstanceProxy::modelClass());
    }
}
