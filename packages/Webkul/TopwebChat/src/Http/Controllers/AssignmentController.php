<?php

namespace Webkul\TopwebChat\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Services\ConversationAccessService;
use Webkul\TopwebChat\Services\LeadOwnershipService;

class AssignmentController
{
    public function __construct(
        protected ConversationAccessService $access,
        protected LeadOwnershipService $leadOwnership
    ) {}

    public function update(Request $request, Conversation $conversation): RedirectResponse
    {
        abort_unless(bouncer()->hasPermission('topweb_chat.inbox.assign'), 403);

        $user = auth()->guard('user')->user();
        $data = $request->validate([
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        if ($conversation->lead_id !== null) {
            abort_unless($data['assigned_user_id'] !== null, 422);
            $this->leadOwnership->transfer(
                $conversation,
                $user,
                (int) $data['assigned_user_id']
            );

            return back()->with('success', trans('topweb_chat::app.assignment.updated'));
        }

        try {
            DB::transaction(function () use ($conversation, $data, $user) {
                $lockedConversation = Conversation::query()
                    ->lockForUpdate()
                    ->findOrFail($conversation->id);

                if (($data['assigned_user_id'] ?? null) === null) {
                    abort_unless(
                        $this->access->canUnassign($user, $lockedConversation),
                        403
                    );

                    $lockedConversation->update(['assigned_user_id' => null]);

                    return;
                }

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
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 403) {
                throw $exception;
            }

            // V-07: perda de corrida informa quem ficou com a conversa.
            $owner = Conversation::query()->find($conversation->id)
                ?->assignedUser?->name
                ?? trans('topweb_chat::app.conversations.unassigned');

            return back()->with(
                'error',
                trans('topweb_chat::app.assignment.taken', ['name' => $owner])
            );
        }

        return back()->with('success', trans('topweb_chat::app.assignment.updated'));
    }
}
