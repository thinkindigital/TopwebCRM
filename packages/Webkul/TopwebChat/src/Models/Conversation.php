<?php

namespace Webkul\TopwebChat\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Contact\Models\PersonProxy;
use Webkul\Lead\Models\LeadProxy;
use Webkul\TopwebChat\Contracts\Conversation as ConversationContract;
use Webkul\User\Models\GroupProxy;
use Webkul\User\Models\UserProxy;

class Conversation extends Model implements ConversationContract
{
    protected $table = 'topweb_chat_conversations';

    protected $fillable = [
        'instance_id',
        'person_id',
        'lead_id',
        'assigned_user_id',
        'assigned_group_id',
        'remote_jid',
        'remote_jid_key',
        'status',
        'priority',
        'unread_count',
        'last_message_at',
        'history_cursor_at',
        'history_backfilled_at',
        'closed_at',
    ];

    protected $casts = [
        'remote_jid' => 'encrypted',
        'last_message_at' => 'datetime',
        'history_cursor_at' => 'datetime',
        'history_backfilled_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function instance()
    {
        return $this->belongsTo(InstanceProxy::modelClass());
    }

    public function person()
    {
        return $this->belongsTo(PersonProxy::modelClass());
    }

    public function lead()
    {
        return $this->belongsTo(LeadProxy::modelClass());
    }

    public function assignedUser()
    {
        return $this->belongsTo(UserProxy::modelClass(), 'assigned_user_id');
    }

    public function assignedGroup()
    {
        return $this->belongsTo(GroupProxy::modelClass(), 'assigned_group_id');
    }

    public function messages()
    {
        return $this->hasMany(MessageProxy::modelClass());
    }

    public function internalNotes()
    {
        return $this->hasMany(InternalNoteProxy::modelClass());
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }
}
