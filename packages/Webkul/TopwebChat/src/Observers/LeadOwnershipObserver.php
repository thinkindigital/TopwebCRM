<?php

namespace Webkul\TopwebChat\Observers;

use Illuminate\Support\Facades\DB;
use Webkul\Lead\Models\Lead;
use Webkul\TopwebChat\Models\Conversation;

class LeadOwnershipObserver
{
    public static function register(): void
    {
        Lead::updated(function (Lead $lead) {
            (new self)->handle($lead);
        });
    }

    public function handle(Lead $lead): void
    {
        if (! $lead->wasChanged('user_id')) {
            return;
        }

        // D04: a projecao acompanha a autoridade na mesma escrita.
        DB::transaction(function () use ($lead) {
            Conversation::query()
                ->where('lead_id', $lead->id)
                ->update(['assigned_user_id' => $lead->user_id]);
        });
    }
}
