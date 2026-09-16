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

        $sync = function () use ($lead): void {
            Conversation::query()
                ->where('lead_id', $lead->id)
                ->lockForUpdate()
                ->update(['assigned_user_id' => $lead->user_id]);
        };

        // D04: a projeção acompanha a autoridade na mesma escrita. Reuse the
        // caller's transaction when Lead ownership is changed from the chat.
        if (DB::transactionLevel() > 0) {
            $sync();
        } else {
            DB::transaction($sync);
        }
    }
}
