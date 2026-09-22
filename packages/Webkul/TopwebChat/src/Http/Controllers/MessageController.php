<?php

namespace Webkul\TopwebChat\Http\Controllers;

use App\Services\SensitiveDataService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\Message;
use Webkul\TopwebChat\Services\ConversationAccessService;
use Webkul\TopwebChat\Services\MessageService;
use Webkul\TopwebChat\Support\TopwebChatError;

class MessageController
{
    public function __construct(
        protected MessageService $messages,
        protected ConversationAccessService $access,
        protected SensitiveDataService $sensitiveData
    ) {}

    public function store(
        Request $request,
        Conversation $conversation
    ): RedirectResponse|JsonResponse {
        abort_unless(bouncer()->hasPermission('topweb_chat.inbox.send'), 403);

        $user = auth()->guard('user')->user();
        $this->access->authorizeView($user, $conversation);

        // S1: upload exige concessão individual; texto continua permitido.
        if (
            ($request->hasFile('media') || $request->hasFile('document'))
            && ! $this->sensitiveData->canView($user)
        ) {
            abort(403);
        }

        $maxKilobytes = max(1, (int) (config('topweb-chat.openwa.media_max_bytes', 52428800) / 1024));

        if ($request->hasFile('media')) {
            $media = $request->validate([
                'media' => ['required', 'file', "max:{$maxKilobytes}"],
                'caption' => ['nullable', 'string', 'max:1000'],
                'operation_key' => ['required', 'uuid'],
            ]);

            try {
                $message = $this->messages->queueMedia(
                    $conversation,
                    $user,
                    $media['media'],
                    $media['caption'] ?? null,
                    $media['operation_key']
                );
            } catch (DomainException $exception) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => $exception->getMessage(),
                    ], 409);
                }

                return back()->with('error', $exception->getMessage());
            }
        } elseif ($request->hasFile('document')) {
            $document = $request->validate([
                'document' => ['required', 'file', "max:{$maxKilobytes}"],
                'caption' => ['nullable', 'string', 'max:1000'],
                'operation_key' => ['required', 'uuid'],
            ]);

            try {
                $message = $this->messages->queueMedia(
                    $conversation,
                    $user,
                    $document['document'],
                    $document['caption'] ?? null,
                    $document['operation_key']
                );
            } catch (DomainException $exception) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => $exception->getMessage(),
                    ], 409);
                }

                return back()->with('error', $exception->getMessage());
            }
        } else {
            $data = $request->validate([
                'content' => ['required_without:media', 'nullable', 'string', 'max:10000'],
                'operation_key' => ['required', 'uuid'],
            ]);

            try {
                $message = $this->messages->queueText(
                    $conversation,
                    $user,
                    (string) ($data['content'] ?? ''),
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
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->serialize($message),
            ], 202);
        }

        return back()->with('success', trans('topweb_chat::app.messages.queued'));
    }

    public function storeBatch(
        Request $request,
        Conversation $conversation
    ): JsonResponse {
        abort_unless(bouncer()->hasPermission('topweb_chat.inbox.send'), 403);

        $user = auth()->guard('user')->user();
        $this->access->authorizeView($user, $conversation);

        // S1/S5: lote inteiro sob a concessão; nada é armazenado sem grant.
        abort_unless($this->sensitiveData->canView($user), 403);

        $maxFiles = max(1, (int) config('topweb-chat.batch.max_files', 10));
        $maxKilobytes = max(1, (int) (config('topweb-chat.openwa.media_max_bytes', 52428800) / 1024));

        $data = $request->validate([
            'attachments' => ['required', 'array', 'min:1', "max:{$maxFiles}"],
            'attachments.*.file' => ['required', 'file', "max:{$maxKilobytes}"],
            'attachments.*.operation_key' => ['required', 'uuid', 'distinct'],
            'content' => ['nullable', 'string', 'max:10000'],
            'content_operation_key' => ['required_with:content', 'uuid'],
        ]);

        $totalBytes = collect($data['attachments'])
            ->sum(fn ($item) => $item['file']->getSize() ?: 0);

        if ($totalBytes > max(1, (int) config('topweb-chat.batch.max_bytes', 104857600))) {
            abort(422, trans('topweb_chat::app.messages.batch_too_large'));
        }

        try {
            $result = $this->messages->queueBatch(
                $conversation,
                $user,
                array_map(
                    fn ($item) => ['file' => $item['file'], 'operation_key' => $item['operation_key']],
                    $data['attachments']
                ),
                isset($data['content'])
                    ? ['content' => $data['content'], 'operation_key' => $data['content_operation_key']]
                    : null
            );
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        }

        return response()->json($result, 202);
    }

    public function retry(
        Request $request,
        Conversation $conversation,
        Message $message
    ): RedirectResponse|JsonResponse {
        abort_unless(bouncer()->hasPermission('topweb_chat.inbox.send'), 403);

        $user = auth()->guard('user')->user();
        $this->access->authorizeView($user, $conversation);

        // S1: retry de mídia exige a mesma concessão do upload.
        if ($message->hasMedia() && ! $this->sensitiveData->canView($user)) {
            abort(403);
        }

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
            'last_error' => $message->last_error,
            'error_code' => TopwebChatError::canonical($message->error_code ?: $message->last_error),
            'trace_id' => $message->trace_id,
            'sent_at' => ($message->sent_at ?? $message->created_at)?->toIso8601String(),
            'can_retry' => $this->messages->canRetry($message),
            'retry_url' => route('admin.topweb_chat.messages.retry', [
                'conversation' => $message->conversation_id,
                'message' => $message->id,
            ]),
        ];
    }
}
