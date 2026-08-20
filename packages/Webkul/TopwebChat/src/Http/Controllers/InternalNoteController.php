<?php

namespace Webkul\TopwebChat\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Repositories\InternalNoteRepository;
use Webkul\TopwebChat\Services\ConversationAccessService;

class InternalNoteController
{
    public function __construct(
        protected InternalNoteRepository $internalNoteRepository,
        protected ConversationAccessService $access
    ) {}

    public function store(Request $request, Conversation $conversation): RedirectResponse
    {
        abort_unless(bouncer()->hasPermission('topweb_chat.inbox.notes'), 403);

        $user = auth()->guard('user')->user();
        $this->access->authorizeView($user, $conversation);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:10000'],
        ]);

        $this->internalNoteRepository->create([
            ...$data,
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
        ]);

        return back()->with('success', trans('topweb_chat::app.notes.created'));
    }
}
