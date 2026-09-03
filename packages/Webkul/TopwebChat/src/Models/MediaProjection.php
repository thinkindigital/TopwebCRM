<?php

namespace Webkul\TopwebChat\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Activity\Models\ActivityProxy;
use Webkul\Activity\Models\FileProxy;
use Webkul\Contact\Models\PersonProxy;
use Webkul\Lead\Models\LeadProxy;

class MediaProjection extends Model
{
    protected $table = 'topweb_chat_media_projections';

    protected $fillable = [
        'message_id',
        'activity_id',
        'activity_file_id',
        'lead_id',
        'person_id',
    ];

    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    public function activity()
    {
        return $this->belongsTo(ActivityProxy::modelClass());
    }

    public function activityFile()
    {
        return $this->belongsTo(FileProxy::modelClass(), 'activity_file_id');
    }

    public function lead()
    {
        return $this->belongsTo(LeadProxy::modelClass());
    }

    public function person()
    {
        return $this->belongsTo(PersonProxy::modelClass());
    }
}
