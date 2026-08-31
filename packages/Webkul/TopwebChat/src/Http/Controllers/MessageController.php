<?php

namespace Webkul\TopwebChat\Http\Controllers;

use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\Message;
use Webkul\TopwebChat\Services\ConversationAccessService;
use Webkul\TopwebChat\Services\MessageService;

class MessageController
{
    public function __construct(
        protected MessageService $messages,
        protected ConversationAccessService $access
    ) {}

    public function store(
        Request $request,
        Conversation $conversation
    ): RedirectResponse|JsonResponse {
        abort_unless(bouncer()->hasPermission('topweb_chat.inbox.send'), 403);

        $user = auth()->guard('user')->user();
        $this->access->authorizeView($user, $conversation);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:10000'],
            'operation_key' => ['required', 'uuid'],
        ]);

        try {
            $message = $this->messages->queueText(
                $conversation,
                $user,
                $data['content'],
                $data['operation_key']
            );
        } catch (DomainException $exception) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage(),
                ], 409);
            }

            return back()->with('error', $exception->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->serialize($message),
            ], 202);
        }

        return back()->with('success', trans('topweb_chat::app.messages.queued'));
    }

    public function retry(
        Request $request,
        Conversation $conversation,
        Message $message
    ): RedirectResponse|JsonResponse {
        abort_unless(bouncer()->hasPermission('topweb_chat.inbox.send'), 403);

        $user = auth()->guard('user')->user();
        $this->access->authorizeView($user, $conversation);

        try {
            $message = $this->messages->retry($message, $conversation, $user);
        } catch (DomainException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 409);
            }

            return back()->with('error', $exception->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => $this->serialize($message)], 202);
        }

        return back()->with('success', trans('topweb_chat::app.messages.retry_queued'));
    }

    private function serialize(Message $message): array
    {
        return [
            'id' => $message->id,
            'direction' => $message->direction,
            'type' => $message->type,
            'content' => $message->content,
            'status' => $message->status,
            'sent_at' => ($message->sent_at ?? $message->created_at)?->toIso8601String(),
            'can_retry' => $this->messages->canRetry($message),
            'retry_url' => route('admin.topweb_chat.messages.retry', [
                'conversation' => $message->conversation_id,
                'message' => $message->id,
            ]),
        ];
    }
}
