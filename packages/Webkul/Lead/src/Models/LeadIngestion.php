<?php

namespace Webkul\Lead\Models;

use Illuminate\Database\Eloquent\Model;

class LeadIngestion extends Model
{
    protected $table = 'lead_ingestions';

    protected $fillable = [
        'source',
        'source_lead_id',
        'idempotency_key',
        'lead_id',
        'person_id',
    ];
}
