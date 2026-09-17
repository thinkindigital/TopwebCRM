<?php

namespace Webkul\TopwebChat\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Webkul\Activity\Models\Activity;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Services\ConversationActivityService;

class ConversationActivityController
{
    public function __construct(protected ConversationActivityService $activities) {}

    public function store(
        Request $request,
        Conversation $conversation
    ): RedirectResponse|JsonResponse {
        $data = $request->validate([
            'type' => ['required', 'string', 'max:50'],
            'schedule_from' => ['required', 'date'],
            'schedule_to' => ['required', 'date', 'after_or_equal:schedule_from'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:10000'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $user = auth()->guard('user')->user();
        $activity = $this->activities->create($conversation, $user, $data);

        if ($request->expectsJson()) {
            return response()->json(['activity_id' => $activity->id], 201);
        }

        return back()->with('success', trans('topweb_chat::app.activities.created'));
    }

    public function update(
        Request $request,
        Conversation $conversation,
        Activity $activity
    ): RedirectResponse|JsonResponse {
        $data = $request->validate([
            'schedule_from' => ['required', 'date'],
            'schedule_to' => ['required', 'date', 'after_or_equal:schedule_from'],
        ]);

        $user = auth()->guard('user')->user();
        $activity = $this->activities->reschedule($conversation, $activity, $user, $data);

        if ($request->expectsJson()) {
            return response()->json(['activity_id' => $activity->id]);
        }

        return back()->with('success', trans('topweb_chat::app.activities.rescheduled'));
    }

    public function complete(
        Request $request,
        Conversation $conversation,
        Activity $activity
    ): RedirectResponse|JsonResponse {
        $user = auth()->guard('user')->user();
        $activity = $this->activities->complete($conversation, $activity, $user);

        if ($request->expectsJson()) {
            return response()->json(['activity_id' => $activity->id]);
        }

        return back()->with('success', trans('topweb_chat::app.activities.completed'));
    }
}
