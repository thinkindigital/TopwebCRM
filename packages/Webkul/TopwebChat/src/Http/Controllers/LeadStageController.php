<?php

namespace Webkul\TopwebChat\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Webkul\Lead\Models\Pipeline;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Services\LeadStageService;

class LeadStageController
{
    public function __construct(protected LeadStageService $leadStages) {}

    public function update(Request $request, Conversation $conversation): RedirectResponse|JsonResponse
    {
        abort_unless(bouncer()->hasPermission('topweb_chat.inbox.stage'), 403);

        $data = $request->validate([
            'lead_pipeline_id' => ['nullable', 'integer'],
            'lead_pipeline_stage_id' => ['required', 'integer'],
        ]);

        $this->leadStages->move(
            $conversation,
            auth()->guard('user')->user(),
            $data['lead_pipeline_stage_id'],
            $data['lead_pipeline_id'] ?? null
        );

        if ($request->expectsJson()) {
            $lead = $conversation->fresh()->lead;

            return response()->json([
                'lead_pipeline_id' => $lead->lead_pipeline_id,
                'lead_pipeline_stage_id' => $lead->lead_pipeline_stage_id,
            ]);
        }

        return back()->with('success', trans('topweb_chat::app.leads.stage_updated'));
    }

    public function stages(
        Request $request,
        Conversation $conversation,
        Pipeline $pipeline
    ): JsonResponse {
        abort_unless(bouncer()->hasPermission('topweb_chat.inbox.stage'), 403);

        $stages = $this->leadStages->stagesForPipeline(
            $conversation,
            auth()->guard('user')->user(),
            $pipeline
        );

        return response()->json(['data' => $stages]);
    }
}
