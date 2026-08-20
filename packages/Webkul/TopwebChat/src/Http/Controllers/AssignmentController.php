<?php

namespace Webkul\TopwebChat\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Services\ConversationAccessService;

class AssignmentController
{
    public function __construct(protected ConversationAccessService $access) {}

    public function update(Request $request, Conversation $conversation): RedirectResponse
    {
        abort_unless(bouncer()->hasPermission('topweb_chat.inbox.assign'), 403);

        $user = auth()->guard('user')->user();
        $data = $request->validate([
            'assigned_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        DB::transaction(function () use ($conversation, $data, $user) {
            $lockedConversation = Conversation::query()
                ->lockForUpdate()
                ->findOrFail($conversation->id);

            abort_unless(
                $this->access->canAssign(
                    $user,
                    $lockedConversation,
                    $data['assigned_user_id']
                ),
                403
            );

            $lockedConversation->update($data);
        });

        return back()->with('success', trans('topweb_chat::app.assignment.updated'));
    }
}
