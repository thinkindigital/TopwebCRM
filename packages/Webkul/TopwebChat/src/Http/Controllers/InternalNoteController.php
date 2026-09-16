<?php

namespace Webkul\TopwebChat\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\InternalNote;
use Webkul\TopwebChat\Repositories\InternalNoteRepository;
use Webkul\TopwebChat\Services\ConversationAccessService;

class InternalNoteController
{
    public function __construct(
        protected InternalNoteRepository $internalNoteRepository,
        protected ConversationAccessService $access
    ) {}

    public function store(Request $request, Conversation $conversation): RedirectResponse|JsonResponse
    {
        abort_unless(bouncer()->hasPermission('topweb_chat.inbox.notes'), 403);

        $user = auth()->guard('user')->user();
        $this->access->authorizeView($user, $conversation);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:10000'],
        ]);

        $note = $this->internalNoteRepository->create([
            ...$data,
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['note_id' => $note->id], 201);
        }

        return back()->with('success', trans('topweb_chat::app.notes.created'));
    }

    public function destroy(
        Request $request,
        Conversation $conversation,
        InternalNote $note
    ): RedirectResponse|JsonResponse {
        abort_unless(bouncer()->hasPermission('topweb_chat.inbox.notes.delete'), 403);

        $user = auth()->guard('user')->user();
        $this->access->authorizeView($user, $conversation);

        abort_unless($note->conversation_id === $conversation->id, 404);
        abort_unless(
            $this->access->isAdministrator($user) || $note->user_id === $user->id,
            403
        );

        Log::info('TopwebChat internal note deleted.', [
            'note_id' => $note->id,
            'conversation_id' => $conversation->id,
            'actor_user_id' => $user->id,
            'created_by_user_id' => $note->user_id,
        ]);

        $note->delete();

        if ($request->expectsJson()) {
            return response()->json(['deleted' => true]);
        }

        return back()->with('success', trans('topweb_chat::app.notes.deleted'));
    }
}
