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

    /**
     * Blind A3 self-claim is operational access, not generic assignment.
     */
    public function claim(Conversation $conversation): RedirectResponse
    {
        abort_unless(bouncer()->hasPermission('topweb_chat.inbox.view'), 403);

        $user = auth()->guard('user')->user();

        try {
            if ($conversation->lead_id !== null) {
                $this->leadOwnership->claim($conversation, $user);
            } else {
                DB::transaction(function () use ($conversation, $user) {
                    $lockedConversation = Conversation::query()
                        ->lockForUpdate()
                        ->findOrFail($conversation->id);

                    abort_unless(
                        $lockedConversation->lead_id === null
                        && $lockedConversation->assigned_user_id === null
                        && $this->access->canAssign($user, $lockedConversation, (int) $user->id),
                        403
                    );

                    $lockedConversation->update(['assigned_user_id' => $user->id]);
                });
            }
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 403) {
                throw $exception;
            }

            return $this->claimConflict($conversation);
        }

        return back()->with('success', trans('topweb_chat::app.assignment.updated'));
    }

    private function claimConflict(Conversation $conversation): RedirectResponse
    {
        $owner = Conversation::query()->find($conversation->id)
            ?->assignedUser?->name
            ?? trans('topweb_chat::app.conversations.unassigned');

        return back()->with(
            'error',
            trans('topweb_chat::app.assignment.taken', ['name' => $owner])
        );
    }

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

            // A 403 on an already-owned conversation is a lost race; an
            // unassigned conversation remains a real authorization failure.
            abort_unless(
                Conversation::query()->whereKey($conversation->id)
                    ->whereNotNull('assigned_user_id')->exists(),
                403
            );
            return $this->claimConflict($conversation);
        }

        return back()->with('success', trans('topweb_chat::app.assignment.updated'));
    }

}
