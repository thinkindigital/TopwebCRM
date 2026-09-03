<?php

namespace Webkul\TopwebChat\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Activity\Models\ActivityProxy;

class Attendance extends Model
{
    protected $table = 'topweb_chat_attendances';

    protected $fillable = [
        'conversation_id',
        'activity_id',
        'sequence',
        'opened_by_message_id',
        'last_message_id',
        'opened_at',
        'last_real_message_at',
        'closed_at',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'last_real_message_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function activity()
    {
        return $this->belongsTo(ActivityProxy::modelClass());
    }

    public function openedByMessage()
    {
        return $this->belongsTo(Message::class, 'opened_by_message_id');
    }

    public function lastMessage()
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }
}
