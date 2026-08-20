<?php

namespace Webkul\TopwebChat\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Services\LeadStageService;

class LeadStageController
{
    public function __construct(protected LeadStageService $leadStages) {}

    public function update(Request $request, Conversation $conversation): RedirectResponse
    {
        abort_unless(bouncer()->hasPermission('topweb_chat.inbox.stage'), 403);

        $data = $request->validate([
            'lead_pipeline_stage_id' => ['required', 'integer'],
        ]);

        $this->leadStages->move(
            $conversation,
            auth()->guard('user')->user(),
            $data['lead_pipeline_stage_id']
        );

        return back()->with('success', trans('topweb_chat::app.leads.stage_updated'));
    }
}
