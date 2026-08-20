<?php

namespace Webkul\TopwebChat\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\TopwebChat\Contracts\InternalNote as InternalNoteContract;
use Webkul\User\Models\UserProxy;

class InternalNote extends Model implements InternalNoteContract
{
    protected $table = 'topweb_chat_internal_notes';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'content',
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
