<?php

namespace Webkul\TopwebChat\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\TopwebChat\Contracts\Message as MessageContract;
use Webkul\User\Models\UserProxy;

class Message extends Model implements MessageContract
{
    protected $table = 'topweb_chat_messages';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'operation_key',
        'provider_message_id',
        'provider_message_key',
        'direction',
        'type',
        'content',
        'status',
        'attempts',
        'source',
        'metadata',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'last_error',
    ];

    protected $casts = [
        'provider_message_id' => 'encrypted',
        'metadata' => 'encrypted:array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(ConversationProxy::modelClass());
    }

    public function user()
    {
        return $this->belongsTo(UserProxy::modelClass());
    }
}
